# silverstripe-massexport

# Introduction

This module dynamically detects any subclasses of `ModelAdmin` and adapts to the `$managed_models` defined within, and also discovers all [UserDefinedForm](https://github.com/silverstripe/silverstripe-userforms) submissions to provide a centralised interface that can be used to export data from any one or more models from within a specified date range which is then zipped into a single file and downloaded.

## Installation

This module only supports installation via composer:

```
composer require steadlane/silverstripe-massexport
```

Run `/dev/build` afterwards and `?flush=1` for good measure for SilverStripe to become aware of this module

## Configuration

Models can be included or excluded via config files.

To include additional classes, add an `additional_models` setting:

```
SteadLane\MassExport\MassExport:
  additional_models:        # Add additional models like so
    - "Namespace\\Class"
```

To exclude classes, add an `excluded_models` setting:

```
SteadLane\MassExport\MassExport:
  excluded_models:          # Exclude models like so
    - "Namespace\\Class"
```

## Contributing

If you feel you can improve this module in any way, shape or form please do not hesitate to submit a PR for review.

## Bugs / Issues

To report a bug or an issue please use our [issue tracker](https://github.com/steadlane/silverstripe-massexport/issues).

## License

This module is distributed under the [BSD-3 Clause](https://github.com/steadlane/silverstripe-massexport/blob/master/LICENSE) license.
