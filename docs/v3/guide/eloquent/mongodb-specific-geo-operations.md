# MongoDB-specific Geo operations

## Near

```php
$bars = Bar::where('location', 'near', [
    '$geometry' => [
        'type' => 'Point',
        'coordinates' => [
            -0.1367563, // longitude
            51.5100913, // latitude
        ],
    ],
    '$maxDistance' => 50,
])->get();
```

## GeoWithin

```php
$bars = Bar::where('location', 'geoWithin', [
    '$geometry' => [
        'type' => 'Polygon',
        'coordinates' => [
            [
                [-0.1450383, 51.5069158],
                [-0.1367563, 51.5100913],
                [-0.1270247, 51.5013233],
                [-0.1450383, 51.5069158],
            ],
        ],
    ],
])->get();
```

## GeoIntersects

```php
$bars = Bar::where('location', 'geoIntersects', [
    '$geometry' => [
        'type' => 'LineString',
        'coordinates' => [
            [-0.144044, 51.515215],
            [-0.129545, 51.507864],
        ],
    ],
])->get();
```

## GeoNear

You are able to make a `geoNear` query on mongoDB.
You don't need to specify the automatic fields on the model.
The returned instance is a collection. So you're able to make the [Collection](https://laravel.com/docs/9.x/collections) operations.
Just make sure that your model has a `location` field, and a [2ndSphereIndex](https://www.mongodb.com/docs/manual/core/2dsphere).
The data in the `location` field must be saved as [GeoJSON](https://www.mongodb.com/docs/manual/reference/geojson/).
The `location` points must be saved as [WGS84](https://www.mongodb.com/docs/manual/reference/glossary/#std-term-WGS84) reference system for geometry calculation. That means, basically, you need to save `longitude and latitude`, in that order specifically, and to find near with calculated distance, you `need to do the same way`.

```php
Bar::find("63a0cd574d08564f330ceae2")->update(
    [
        'location' => [
            'type' => 'Point',
            'coordinates' => [
                -0.1367563,
                51.5100913
            ]
        ]
    ]
);
$bars = Bar::raw(function ($collection) {
    return $collection->aggregate([
        [
            '$geoNear' => [
                "near" => [ "type" =>  "Point", "coordinates" =>  [-0.132239, 51.511874] ],
                "distanceField" =>  "dist.calculated",
                "minDistance" =>  0,
                "maxDistance" =>  6000,
                "includeLocs" =>  "dist.location",
                "spherical" =>  true,
            ]
        ]
    ]);
});
```