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

To check the code before you make a pull request:

```shell
docker compose exec phpfpm composer normalize --dry-run
docker compose exec phpfpm vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec phpfpm vendor/bin/twig-cs-fixer lint
docker compose run --rm prettier --no-editorconfig '**/*.{yml,yaml}' --check
docker compose run --rm markdownlint markdownlint '**/*.md'
```

The GitHub Actions workflows in `.github/workflows/` run the same checks.

## Related projects

- [itk-enter/enter](https://github.com/itk-enter/enter): the data platform that this project reads from.
- [itk-enter/enter-map-examples](https://github.com/itk-enter/enter-map-examples): the prototypes for the map, published
  at <https://itk-enter.github.io/enter-map-examples/>.

## Contributing

Make a pull request to `develop`. See [CLAUDE.md](CLAUDE.md) for the conventions for branches, commits and the
CHANGELOG.
