#!/usr/bin/env sh
#
# SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# This script exports a user, deletes it and imports it again, and searches for missing data
# It is designed to work with docker setup from https://github.com/juliushaertl/nextcloud-docker-dev

# Stop at first error
set -e

user=$1
occ="docker exec /nextcloud_nextcloud_1 occ"
mysqldump="mysqldump --skip-opt -h $(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' nextcloud_database-mysql_1) -P 3306 -u nextcloud -pnextcloud"

# Save state of user and database before migration

echo "Before Migration" > /tmp/beforeMigration

echo $occ user:setting $user >> /tmp/beforeMigration
$occ user:setting $user >> /tmp/beforeMigration

echo $occ user:info $user >> /tmp/beforeMigration
$occ user:info $user >> /tmp/beforeMigration

echo $occ user:lastseen $user >> /tmp/beforeMigration
$occ user:lastseen $user >> /tmp/beforeMigration

$mysqldump nextcloud > /tmp/beforeMigration.sql

# Export user to /tmp and store export path

export_out=$($occ user:export $user /tmp)
echo "$export_out"
path=$(echo "$export_out"| grep "Moved the export to " | cut -d " " -f5)

# Delete user

#~ $occ user:delete $user # occ user:delete is broken so we use OCS api instead
curl -H "OCS-APIRequest: true" -X DELETE http://admin:admin@$(docker inspect -f '{{range.NetworkSettings.Networks}}{{.IPAddress}}{{end}}' nextcloud_nextcloud_1)/ocs/v1.php/cloud/users/$user

# Import the user again

$occ user:import $path

# Save state of user and database after migration

echo "After Migration" > /tmp/afterMigration

echo $occ user:setting $user >> /tmp/afterMigration
$occ user:setting $user >> /tmp/afterMigration

echo $occ user:info $user >> /tmp/afterMigration
$occ user:info $user >> /tmp/afterMigration

echo $occ user:lastseen $user >> /tmp/afterMigration
$occ user:lastseen $user >> /tmp/afterMigration

$mysqldump nextcloud > /tmp/afterMigration.sql

# Show the differences (hopefully not much)

diff --side-by-side /tmp/beforeMigration /tmp/afterMigration || true

# Summary of sql differences ignoring ids

awk 'NR==FNR{a[$0]=1;next}!a[$0]' /tmp/afterMigration.sql /tmp/beforeMigration.sql | sed -e "s/VALUES ([0-9]*,/VALUES (/"|sort|sed -e 's/,/,\n\t/g' > /tmp/before.sql
awk 'NR==FNR{a[$0]=1;next}!a[$0]' /tmp/beforeMigration.sql /tmp/afterMigration.sql | sed -e "s/VALUES ([0-9]*,/VALUES (/"|sort|sed -e 's/,/,\n\t/g' > /tmp/after.sql

echo "You can compare /tmp/beforeMigration.sql and /tmp/afterMigration.sql to see raw database changes"
