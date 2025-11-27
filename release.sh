#!/usr/bin/env bash
#
# SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Stop at first error
set -e

if ! command -v gh > /dev/null; then
    echo "Could not find the GitHub CLI, please install it from https://github.com/cli/cli"
    exit 1
fi

# Use version from changelog
version=$(grep '^## \[' CHANGELOG.md|head -n1|cut -d'[' -f2|cut -d']' -f1);
# version=$1
# The target branch, defaults to the current branch
target=${2:-$(git branch --show-current)}
# The tag
tag=v$version

if ! [[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-z]+\.[0-9]+)?$ ]]; then
    echo "Invalid version, please enter a valid semantic version"
    exit 1
fi

if [[ $(git branch --show-current) != $target ]]; then
    if ! git switch $target > /dev/null; then
        echo "Target branch does not exist, please enter a valid branch name"
        exit 1
    fi
fi

echo "Releasing version $version on branch $target";

# Ask for confirmation
read -r -p "Are you sure? [y/N] " input

case $input in
    [yY][eE][sS]|[yY])
        echo "You say Yes"
        ;;
    [nN][oO]|[nN])
        echo "You say No"
        exit 1
        ;;
    *)
        echo "Invalid input..."
        exit 1
        ;;
esac

# Ask for confirmation
read -r -p "Create commit and tag? [y/N] " input

case $input in
    [yY][eE][sS]|[yY])
        echo "You say Yes"

        # Bump version in info.xml
        sed -i -E "s|^\t<version>.+</version>|\t<version>$version</version>|" appinfo/info.xml

        # Add changed files to git
        git add CHANGELOG.md
        git add appinfo/info.xml

        # Bump npm version, commit and tag
        npm version --allow-same-version -f $version

        # Show the result
        git log -1 -p

        # Add signoff
        git commit --amend -s
        ;;
    *)
        echo "You say No"
        ;;
esac

# Ask for confirmation
read -r -p "Push and draft releases? [y/N] " input

case $input in
    [yY][eE][sS]|[yY])
        echo "You say Yes"

        # Push commit
        git push git@github.com:nextcloud/user_migration.git

        # Push tag
        git push git@github.com:nextcloud/user_migration.git $tag
        git push git@github.com:nextcloud-releases/user_migration.git $tag

        # Draft GitHub releases
        gh release create --repo nextcloud/user_migration --draft --generate-notes --target $target --title $tag --verify-tag $tag
        gh release create --repo nextcloud-releases/user_migration --draft --generate-notes --title $tag --verify-tag $tag
        ;;
    *)
        echo "You say No"
        ;;
esac

echo "Check and publish the drafted release on https://github.com/nextcloud/user_migration/releases"
echo "Check and publish the drafted release on https://github.com/nextcloud-releases/user_migration/releases"
