# TYPO3 parallel scheduler task processing test

Scheduler tasks with "Allow Parallel Execution" not ticked aren't allowed to
have multiple parallel running executions. However, current TYPO3 v11, v12 and
v13 releases contain defects that allow parallel scheduler:run invocations to
sometimes execute the same no-multiple-execution task in parallel. This
repository contains a minimal reproduction.

## Setup

```shell
ddev delete --omit-snapshot --yes
ddev start
ddev composer update --with 'typo3/cms-core:^13.1' # or ^12.4, or ^11.5
ddev typo3 install:setup --force --no-interaction
ddev typo3 create-scheduler-command
```

### Additional Setup for TYPO3 v11

```shell
cp config/system/additional.php public/typo3conf/AdditionalConfiguration.php
```

## Reproduction

There is a helper program in the ddev webserver service container that bursts
multiple `vendor/bin/typo3 scheduler:run` console commands in a loop:

```shell
ddev exec reproduction-helper
```

## Recovering from broken state

To recover from a broken state that may have been created when aborting
`vendor/bin/typo3 scheduler:run` processes:

```shell
ddev typo3 scheduler:run --task 1 --stop
rm single-execution-guard
```

## Apply patches

```shell
patch -p1 -d vendor/typo3/cms-scheduler < bugfix-typo3main.patch
# for TYPO3 v11
patch -p1 -d vendor/typo3/cms-scheduler < bugfix-typo3v11.patch
```
