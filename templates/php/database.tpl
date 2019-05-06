        '{name}' => array(
            // The name will be used to refer to the database on the command-line interface.
            // If you want it to be something other than the database name, specify it here.
            'name' => '{name}',

            // One of the supported pdo_drivers()
            'driver' => {driver},

            // The name of the database to connect to, or path to an sqlite database.
            'database' => {database},

            'host' => {host},
            'port' => {port},
            'username' => {username},
            'password' => {password},
            'charset' => {charset},

            // The name of the table used to record the migrations that have been
            // performed on this database connection.
            'changelog' => {changelog},

            // A sub folder of the migration path, this will contain the generated
            // migrations for this database.
            'path' => {path},
        ),
