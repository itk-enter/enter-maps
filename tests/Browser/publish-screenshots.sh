#!/bin/sh
# Puts the screenshots in var/screenshots/ on the branch pr-screenshots, in a
# folder for the pull request, and pushes the branch. Then it writes the
# Markdown for the description of the pull request, and deletes the
# screenshots in var/screenshots/.
#
# The branch has no history in common with develop, so the images never go
# into the code. Do not merge or delete the branch: the descriptions of old
# pull requests link to it.
#
# Usage: tests/Browser/publish-screenshots.sh <number of the pull request>
# "task screenshot:publish -- 26" runs this script on the host.
set -eu

pr=${1:-}
case $pr in
  '' | *[!0-9]*)
    echo "Give the number of the pull request, for example: task screenshot:publish -- 26" >&2
    exit 1
    ;;
esac

source_dir=var/screenshots
if ! ls "$source_dir"/*.png > /dev/null 2>&1; then
  echo "There are no screenshots in $source_dir. Run \"task screenshot\" first." >&2
  exit 1
fi

branch='pr-screenshots'
worktree=var/pr-screenshots-worktree
# The owner and the name of the repository, for example itk-enter/enter-maps.
repository=$(git remote get-url origin | sed -E 's#^.*github\.com[:/]##; s#\.git$##')

rm -rf "$worktree"
git worktree prune
if git fetch --quiet origin "$branch" 2> /dev/null; then
  git worktree add --quiet --force -B "$branch" "$worktree" "origin/$branch"
else
  git worktree add --quiet --orphan -b "$branch" "$worktree"
fi

mkdir -p "$worktree/pr-$pr"
cp "$source_dir"/*.png "$worktree/pr-$pr/"
git -C "$worktree" add "pr-$pr"
if git -C "$worktree" diff --cached --quiet; then
  echo "The screenshots of PR-$pr are already on $branch."
else
  git -C "$worktree" commit --quiet --message "Added the screenshots of PR-$pr"
  git -C "$worktree" push --quiet origin "$branch"
fi

echo
echo "Markdown for the description of PR-$pr:"
echo
for file in "$source_dir"/*.png; do
  name=$(basename "$file")
  url="https://raw.githubusercontent.com/$repository/$branch/pr-$pr/$name"
  case $name in
    *-phone.png) echo "<img src=\"$url\" alt=\"${name%.png}\" width=\"300\">" ;;
    *) echo "![${name%.png}]($url)" ;;
  esac
  echo
done

git worktree remove --force "$worktree"
rm "$source_dir"/*.png
