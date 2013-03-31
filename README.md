Laravel Eloquent MongoDB
========================

This is an alternative Eloquent model that supports MongoDB. The code structure is not great, and there is little functionality at this moment.

Some code is based on https://github.com/navruzm/lmongo, that has way more complete features, but works a bit different than Eloquent.

Usage
-----

Tell your model to use the MongoDB model and a MongoDB collection:

    use Jenssegers\Mongodb\Model as Eloquent

    class MyModel extends Eloquent {

        protected $collection = 'mycollection';

    }

Configuration
-------------

The model will automatically check the Laravel database configuration array for a 'mongodb' item.

    'mongodb' => array(
        'host'     => 'localhost',
        'port'     => 27017,
        'database' => 'database',
    ),

You can also specify the connection name in the model:

    class MyModel extends Eloquent {

        protected $connection = 'mongodb2';

    }