# Getting Started

## Install

```bash
composer install
```

## Analyze A Project

```bash
php bin/engine.php init /path/to/project
php bin/engine.php analyze /path/to/project
php bin/engine.php metrics /path/to/project
```

## Inspect Findings

```bash
php bin/engine.php cycles /path/to/project
php bin/engine.php architecture /path/to/project
php bin/engine.php orphans /path/to/project --audit
php bin/engine.php impact /path/to/project "App\\Service\\OrderService::process"
```

## Export Context For AI

```bash
php bin/engine.php context /path/to/project --minimal
php bin/engine.php context /path/to/project --entrypoint="App\\Service\\OrderService::process"
```

## Save A Baseline

```bash
php bin/engine.php snapshot /path/to/project --save=before-change
php bin/engine.php snapshot /path/to/project --compare=before-change
php bin/engine.php architecture-gate /path/to/project --baseline=before-change --fail-on=new
```
