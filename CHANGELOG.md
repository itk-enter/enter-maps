# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

* [PR-23](https://github.com/itk-enter/enter-maps/pull/23)
  Removed the value `off` of the embed option `sidebar`
* [PR-20](https://github.com/itk-enter/enter-maps/pull/20)
  Added the map form in the builder in four steps, with a fixed filter for each layer
* [PR-15](https://github.com/itk-enter/enter-maps/pull/15)
  Added the MIT license
* [PR-19](https://github.com/itk-enter/enter-maps/pull/19)
  Added the list of maps in the builder at `/builder`, with the embed code, delete and restore
* [PR-18](https://github.com/itk-enter/enter-maps/pull/18)
  Added browser tests with Playwright and axe-core, and the `task test:browser` command
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
