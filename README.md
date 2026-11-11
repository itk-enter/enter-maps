# Enter maps

Enter maps shows data from [Enter](https://github.com/itk-enter/enter) on maps that other sites can embed in an
`<iframe>`. It also has a builder: a form that makes a map configuration and gives the embed code.

> [!NOTE]
> The project is in the plan stage. The repository has no application code yet.

## Parts

- **The map** (`/embed/{renderer}`). A full-page map with an accessible sidebar. The settings come from the URL or
  from a stored configuration. There are two renderers: Leaflet and Septima Widget.
- **The builder** (`/builder`). A form that makes a map configuration, shows a live preview and gives the embed code.
  It stores the configuration as JSON in MariaDB.
- **The data proxy** (`/data/{dataSet}.geojson`). The map gets its data here, not from Enter directly. The proxy
  reads from Enter, pages through the results and caches them.

This project only reads from Enter. It does not write to Enter or to its broker.

## Tech stack

- Symfony on the [ITK Dev Docker template](https://github.com/itk-dev/devops_itkdev-docker): `phpfpm`, `nginx`,
  `mariadb` and Traefik.
- Doctrine ORM and migrations.
- Twig, Symfony UX Twig Components and Live Components, Stimulus, and AssetMapper. There is no Node build step.
- Leaflet 1.9.x and Septima Widget.
- Adressevælger from Klimadatastyrelsen for address search.

## Development

The project uses the `symfony-8` template from
[itk-dev/devops_itkdev-docker](https://github.com/itk-dev/devops_itkdev-docker). You need Docker and the
`itkdev-docker-compose` script from that repository.

To start the project:

```shell
itkdev-docker-compose traefik:start
docker compose up --detach
docker compose exec phpfpm composer install
itkdev-docker-compose open
```

The site is at <http://enter-maps.local.itkdev.dk/>.

`docker-compose.override.yml` changes the template for this project. Docker Compose reads it automatically. It removes
Mailpit, because the project does not send e-mail.

### Data from a local Enter

This project reads data from [Enter](https://github.com/itk-enter/enter). To use a local Enter, start the two projects
on the same Docker host. The `phpfpm` container of this project is on the `frontend` network. The `nginx` container of
Enter is also on that network, as `enter-nginx-1`.

`ENTER_BASE_URI` in `.env` is `http://enter-nginx-1:8080` by default. If Enter uses a different
`COMPOSE_PROJECT_NAME`, set the container name in `.env.local`.

To make sure that the connection works:

```shell
docker compose exec phpfpm curl --silent http://enter-nginx-1:8080/sources.json
docker compose exec phpfpm curl --silent 'http://enter-nginx-1:8080/ngsi-ld/v1/types'
```

If you change `docker-compose.override.yml` while the containers run, run `docker compose up --detach`. Then run
`docker compose restart nginx`, because nginx keeps the old address of `phpfpm`.

`Taskfile.yml` holds shortcuts for [Task](https://taskfile.dev/). Run `task` to see the list.

To run a Symfony console command:

```shell
task console -- debug:router
```

To check the code before you make a pull request:

```shell
task coding-standards:check
```

To correct the code style:

```shell
task coding-standards:apply
```

There is also a task for each type of file, for example `task coding-standards:php:check`.

To run PHPStan:

```shell
task code-analysis
```

To find code that [Rector](https://getrector.com/) can make better, and to apply the changes:

```shell
task rector:check
task rector:apply
```

`rector.php` holds the rules. Read the changes from `task rector:apply` before you commit them.

To run the tests:

```shell
task test
```

To run the tests with code coverage:

```shell
task test:coverage
```

The task fails if the coverage of `src/` is lower than `COVERAGE_THRESHOLD` in `task/test.yml`. The `Tests`
workflow runs the same task. Put unit tests in `tests/Unit` and integration tests in `tests/Integration`.

The GitHub Actions workflows in `.github/workflows/` run the same checks.

## Deployment

[Woodpecker](https://woodpecker-ci.org/) deploys a `release/*` branch to the staging server. The steps are in
`.woodpecker/stg.yml`. Woodpecker gets the server and the login from these secrets: `id`, `secret`, `stg_host`,
`stg_path` and `user`.

On the server, set `ENTER_BASE_URI` in `.env.local` to the address of Enter.

## Related projects

- [itk-enter/enter](https://github.com/itk-enter/enter): the data platform that this project reads from.
- [itk-enter/enter-map-examples](https://github.com/itk-enter/enter-map-examples): the prototypes for the map, published
  at <https://itk-enter.github.io/enter-map-examples/>.

## Contributing

Make a pull request to `develop`. See [CLAUDE.md](CLAUDE.md) for the conventions for branches, commits and the
CHANGELOG.
