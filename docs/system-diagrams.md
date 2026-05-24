# System Diagrams

Flow Engine can emit Mermaid source for dependency and application maps.

```bash
php bin/engine.php diagram . --view=flowchart
php bin/engine.php diagram . --view=component
php bin/engine.php diagram . --view=c4context
```

For multiple projects:

```bash
php bin/engine.php appmap service-a service-b
php bin/engine.php diagram service-a service-b --type=dependency
```

HTML graph exports embed Mermaid source directly and do not require external runtime scripts.
