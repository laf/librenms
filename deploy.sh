#!/bin/bash
set -e # Exit with nonzero exit code if anything fails

SOURCE_BRANCH="testing-docs"
TARGET_BRANCH="gh-pages"

# Pull requests and commits to other branches shouldn't try to deploy, just build to verify
if [ "$TRAVIS_PULL_REQUEST" != "false" -o "$TRAVIS_BRANCH" != "$SOURCE_BRANCH" ]; then
    echo "Skipping deploy; just doing a build."
    npm run spec
    exit 0
fi

# Save some useful information
rev=$(git rev-parse --short HEAD)

# Clone the existing gh-pages for this repo into out/
# Create a new empty branch if gh-pages doesn't exist yet (should only happen on first deply)
mkdir -p doc/html
(
    cd doc/html
    git init
    git config user.name "${GIT_NAME}"
    git config user.email "${GIT_EMAIL}"
    cp mkdocs.yml mkdocs.yml.orig
    echo "site_url: https://laf.github.io/docs}" >> mkdocs.yml
    echo "markdown_extensions:" >> mkdocs.yml
    echo "    - pymdownx.superfences" >> mkdocs.yml

    pip install --user mkdocs
    pip install --user pymdown-extensions
    echo "Running mkdocs"
    cd ../../
    mkdocs build --clean
    mv mkdocs.yml.orig mkdocs.yml
    echo "Moving on to commiting"

    cd doc/html
    touch .
    echo "Add all files ${GIT_NAME} and ${GIT_EMAIL}"
    git add -A .
    echo "Commit"
    git commit -m "Rebuild pages to gh-pages"
    echo "Push"
    git push -q --force "https://${GH_TOKEN}@${GH_REF}" master:gh-pages > /dev/null 2>&1
)

# If there are no changes (e.g. this is a README update) then just bail.
if [ -z `git diff --exit-code` ]; then
    echo "No changes to the spec on this push; exiting."
    exit 0
fi

