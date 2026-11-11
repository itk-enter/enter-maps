# CLAUDE.md

Instructions for Claude Code and other AI agents in this repository. The audience is the agent. For
documentation for people, see [README.md](README.md).

If an instruction here conflicts with the user's global `~/.claude/CLAUDE.md`, this file wins.

## Project overview

Enter maps shows data from [Enter](https://github.com/itk-enter/enter) on maps that other sites embed in an
`<iframe>`.

The design, the phases and the open questions are in `plan.md` in the project root. The file is not in Git, so
it is possible that it does not exist. If it exists, read it before you start a task.

The project has three parts:

- **The map**, `/embed/{renderer}`. The settings come from the URL (`c`) or from a stored configuration (`{id}`). The
  renderers are `leaflet` and `septima`.
- **The builder**, `/builder`. A Live Component form that stores a `MapConfiguration` and gives the embed code.
- **The data proxy**, `/data/{dataSet}.geojson`. It reads from Enter, pages through the results, caches them and
  returns GeoJSON.

Rules that apply to all parts:

- This project only reads from Enter. Do not write to Enter or to its broker.
- The browser never calls Enter. All data goes through the data proxy.
- The proxy accepts only data sets that are in Enter's `/sources.json`.
- Credentials for Enter stay on the server. Read them from environment variables, for example `ENTER_BASIC_AUTH`.
- `config` is the source of the map. The converters make `map_json.leaflet` and `map_json.septima` from it on each
  save. Do not edit `map_json` by hand.
- Validate `config` against the JSON Schema in `config/schema/`. Do not remove support for an old schema version,
  because embed codes stay on other sites for a long time.
- Field definitions in `config` are data, not code. Do not put JavaScript functions into `config`.
- The map must have zero axe-core violations on both renderers.

## Tech stack

- Symfony on the ITK Dev Docker template for Symfony: `phpfpm`, `nginx`, `mariadb` and Traefik.
- Doctrine ORM and migrations, with MariaDB.
- Twig, `symfony/ux-twig-component`, `symfony/ux-live-component` and Stimulus.
- AssetMapper with `importmap.php`. Do not add a Node build step.
- Symfony Form and Validator for the builder.
- `symfony/http-client` with a scoped client for Enter, and Symfony Cache for the data proxy.
- Leaflet 1.9.x from the importmap.
- Septima Widget as an ES module from its CDN, with a pinned version.
- Adressevælger from Klimadatastyrelsen for address search.
- PHPUnit for unit tests. Playwright and axe-core for browser tests.

## Project structure

The Symfony skeleton and the ITK Dev Docker template are installed. The application code does not exist yet. This is
the planned structure:

```text
config/schema/map-config.v1.json   JSON Schema for config, version 1
src/Controller/                    EmbedController, DataProxyController, BuilderController
src/Entity/MapConfiguration.php    The stored configuration
src/                               Converters for each renderer, the data proxy and the config value object
templates/                         Twig templates and Twig components for the sidebar, list and detail view
assets/controllers/                Stimulus controllers for the map, list, detail, search and filters
tests/                             PHPUnit tests, with snapshot files for the converters
```

When the scaffold exists, update this section to agree with the real structure.

## Coding standards

The ITK Dev templates supply the coding standards and the Taskfile tasks. PHPStan runs at level 6 without a baseline.
Do not add a baseline file. Correct the error.

Before you make a pull request, run `task coding-standards:check`, `task code-analysis`, `task rector:check` and
`task test:coverage`. Commit the changes from `task rector:apply` separately, for example "Applied Rector".

The test coverage of `src/` must not be lower than `COVERAGE_THRESHOLD` in `task/test.yml`. Do not write the value
in other files. Write a test for each new class in `src/`. Put unit tests in `tests/Unit` and integration tests in
`tests/Integration`.

### Language: ASD-STE100

All English text that people read is in ASD-STE100 Simplified Technical English. This applies to:

- Docblocks and comments in PHP, Twig and JavaScript.
- Markdown files: `README.md`, `docs/`, ADRs and this file.
- `CHANGELOG.md` entries, commit messages and PR descriptions.
- Console output and log messages.

The rules that apply most often:

- Write sentences of 20 words or fewer for instructions, and 25 words or fewer for descriptions.
- Write one instruction in each sentence. Use the imperative: "Run the import." Put a condition first: "If the
  broker is not available, start Scorpio."
- Write one topic in each paragraph, with 6 sentences or fewer.
- Use the active voice: "The merge deletes the stale entities", not "The stale entities are deleted".
- Use the simple tenses: present, past and future.
- Use each word with one meaning only. Use the same term for the same thing each time: "data set", "source",
  "merge", "merged entity".
- Do not use words that end in "-ing" as verbs or nouns. "To merge the records", not "merging the records".
- Use "the", "a" and "an". Do not leave them out to make text shorter.
- Do not use noun clusters of more than three words. Use "of" or write the text again.
- Use a vertical list for steps or for three or more items.

Code identifiers, technical names and quoted text are not changed. Examples are class names, routes, NGSI-LD terms
and the Danish titles of data sets. If you are not sure that a word is approved, use the most simple word with one
meaning.

Apply the rules to the text that you write or change. Do not write old text again in an unrelated change.

## Coding practices

These are conventions in addition to the linter rules in "Coding standards".

### Use symfony.com/doc for Symfony features

Before you add or change a feature on a Symfony component, read the applicable chapter on
<https://symfony.com/doc>. Examples are console commands, event listeners, HTTP clients and Twig. Use the approach
that the documentation shows.

If the documentation shows more than one approach, use the one that this codebase uses. This codebase uses
attributes and autowiring: `#[AsCommand]`, `#[AsEventListener]`, `#[Autowire]`, `#[When('dev')]` and scoped HTTP
clients. If nothing similar exists, use the most recent approach in the documentation.

If a change uses a Symfony approach for the first time, give the documentation URL in the PR description.

### Controllers stay thin

A controller receives a request, calls a service and returns a response. Put logic in a service class. A controller
class has a one-line docblock or none.

### Docblocks explain the code

A docblock tells what the code does and how to use it. If the reason is necessary to understand the code, write the
reason in plain words in the docblock.

Do not refer to project history in code. Do not write:

- ADR numbers (`ADR 004`, `see docs/adr/...`).
- Issue, ticket or PR numbers.
- Follow-up work, milestones or sprints.

Put this context in the PR description, the commit message or the CHANGELOG.

You can write a `@todo` for a known defect that the code does not fix yet. The `@todo` tells what to correct, without
an issue number. Do not write a `@todo` for a new feature or an idea. Do not remove a `@todo` while the defect exists.

You can write an example in the docblock of a class. Do not write an example in the docblock of a method or a
function, or in a comment in the code.

A class or method that other code extends needs more than `@param` lines.

- When the code calls the method.
- What each parameter holds.
- What the method must return.

Put a small, concrete example of the method in the docblock of its class.

Give `@param` and `@return` when they tell more than the signature, for example an array shape or a `list<…>`.

## Branching and PRs

- The base branch is **`develop`**. Make a PR to `develop` in `itk-enter/enter-maps`.
- Branch name: **`feature/<ticket>-<short-slug>`**, for example `feature/8395-developer-map`. If there is no ticket,
  use `feature/<short-slug>`.
- Open a new PR as a draft.
- A PR branch holds only its own commits. In a stack of PRs, make each branch from its parent PR branch only. Do not
  merge a branch that combines many PRs into a PR branch. If you do, that PR brings the other PRs into `develop`
  without review.
- The CI workflows in `.github/workflows/` come from
  [`itk-dev/devops_itkdev-docker`](https://github.com/itk-dev/devops_itkdev-docker). Do not edit them here. Make a PR
  upstream.
- Before a merge, all CI checks must pass. One check makes sure that `CHANGELOG.md` changed.

### PR description

Use the sections in `.github/PULL_REQUEST_TEMPLATE.md`: "Link to ticket", "Description", "Screenshot of the result"
and "Checklist". Keep the order of the sections. Write in ASD-STE100.

- A reviewer must understand the description in 30 seconds.
- Each bullet is one or two lines. If a bullet needs more, make two bullets.
- Give only the primary files of the change. The diff shows all the files.
- In the checklist, tick only the items that are true. If a test fails, give its name and tell if it also fails on
  `develop`.

## Commits

Write the subject in the past tense, with a capital letter and no prefix. This repository does not use Conventional
Commits.

```text
Added the conflation fetch step
Grouped the conflation classes into Data, Spatial, Pipeline and Io
Applied coding standards
```

- Keep the subject shorter than 70 characters.
- Use the body to tell why. Write the body in ASD-STE100.
- Make a separate commit for a coding-standards run, for example "Applied coding standards".

## CHANGELOG

`CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). There is no release yet. All entries
go under `## [Unreleased]`.

Each PR adds one entry at the top of `[Unreleased]`. The entry starts with a link to the PR, so add it after the PR
has a number:

```markdown
* [PR-82](https://github.com/itk-enter/enter/pull/82)
  Listed the merged data sets on /sources, in sources.json and on the map
```

### Entry style

An entry tells **what** changed. It does not tell why, how or which files.

- Start with a verb in the past tense: "Added", "Changed", "Fixed", "Removed".
- Write one or two lines. Three lines is the maximum. If an entry needs more, split the PR or cut the entry.
- Do not give the reason, the alternatives or the mechanism. Put them in the PR description or the commit body.
- Name one or two identifiers at most: a command, a class, a route or an environment variable. Do not give a list
  of files.
- Write one entry for each PR. Do not put an unrelated change into the entry.
- If a later PR in the same release reverts or replaces a change, edit or delete the old entry.
- Write in ASD-STE100.

Good:

```markdown
* [PR-83](https://github.com/itk-enter/enter/pull/83)
  Grouped the conflation classes into Data, Spatial, Pipeline and Io
```

Bad, with the reason, the mechanism and a file list in the entry:

```markdown
* [PR-83](https://github.com/itk-enter/enter/pull/83)
  The conflation classes were grouped into folders, because src/Conflation had become too large to read.
  Record, Cluster and StatedValue were moved to Data, and the tests were moved to match.
```
