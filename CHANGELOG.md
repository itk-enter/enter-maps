# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

* [PR-17](https://github.com/itk-enter/enter-maps/pull/17)
  Added the embed page for Leaflet at `/embed/leaflet/{id}`, with Tailwind, Stimulus and Twig components
* [PR-16](https://github.com/itk-enter/enter-maps/pull/16)
  Added the `MapConfiguration` entity and the `app:map-config:import` command
* [PR-13](https://github.com/itk-enter/enter-maps/pull/13)
  Added icons for the layers of a map: a list of icons, an SVG from the editor and a default pin
* [PR-12](https://github.com/itk-enter/enter-maps/pull/12)
  Added `LeafletConverter`, which makes the settings of the Leaflet map from the map configuration
* [PR-11](https://github.com/itk-enter/enter-maps/pull/11)
  Added the JSON Schema of the map configuration and `MapConfigParser`
* [PR-7](https://github.com/itk-enter/enter-maps/pull/7)
  Added the data proxy for Enter at `/data/{dataSet}.geojson`
* [PR-8](https://github.com/itk-enter/enter-maps/pull/8)
  Added Doctrine with MariaDB
* [PR-6](https://github.com/itk-enter/enter-maps/pull/6)
  Added Rector with `task rector:check` and `task rector:apply`
* [PR-5](https://github.com/itk-enter/enter-maps/pull/5)
  Added PHPUnit with a code coverage gate and `task test:coverage`
* [PR-3](https://github.com/itk-enter/enter-maps/pull/3)
  Added PHPStan, the Woodpecker deploy to staging and the PR template
* [PR-2](https://github.com/itk-enter/enter-maps/pull/2)
  Added the Symfony 8.1 skeleton and the ITK Dev `symfony-8` Docker template, with a connection to a local Enter
* [PR-1](https://github.com/itk-enter/enter-maps/pull/1)
  Added the project README, CHANGELOG and CLAUDE.md, and removed plan.md from Git

[Unreleased]: https://github.com/itk-enter/enter-maps
