# MongoDB Laravel

This application uses the [MongoDB Laravel](https://github.com/mongodb/laravel-mongodb) package, so it likely has one or more `mongodb` driver connections. Boost's database tools assume a SQL database and won't work against those.

## Before using Boost's database tools

Before running any Boost database tool, check whether the target connection uses the `mongodb` driver. If the driver isn't already known, use Boost's `database-connections` tool to find out.

If it does, you MUST use a MongoDB Laravel equivalent tool instead, if available:

| Boost tool        | MongoDB Laravel tool |
|-------------------|----------------------|
| `database-schema` | `database-info`      |
