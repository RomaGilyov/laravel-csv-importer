<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Main csv import configurations
    |-------------------------------------------------------------------------- 
    |
    | `cache_driver` - keeps all progress and final information, it also allows
    |   the mutex functionality to work, there are only 3 cache drivers supported:
    |   redis, file and memcached
    |
    | `mutex_lock_time` - how long script will be executed and how long
    |   the import process will be locked, another words if we will import
    |   list of electric guitars we won't be able to run another import of electric
    |   guitars at the same time, to avoid duplicates and different sorts of
    |   incompatibilities. The value set in minutes.
    |
    | `memory_limit` - if you want store all csv values in memory or something like that,
    |   you may increase amount of memory for the script
    |
    | `encoding` - which encoding we have, UTF-8 by default  
    |
    */

    'cache_driver' => env('CACHE_DRIVER', 'file'),

    'mutex_lock_time' => 300,
    
    'memory_limit' => 128,
    
    /*
     * An import class's short name (without namespace) by default
     */
    'mutex_lock_key' => null,

    /*
     * Encoding of given csv file
     */
    'input_encoding' => 'UTF-8',

    /*
     * Encoding of processed csv values
     */
    'output_encoding' => 'UTF-8',

    /*
     * Specify which date format the given csv file has
     * to use `date` ('Y-m-d') and `datetime` ('Y-m-d H:i:s') casters,
     * if the parameter will be set to `null` `date` caster will replace
     * `/` and `\` and `|` and `.` and `,` on `-` and will assume that
     * the given csv file has `Y-m-d` or `d-m-Y` date format
     */
    'csv_date_format' => null,

    'delimiter' => ',',

    'enclosure' => '"',

    /*
     * Warning: The library depends on PHP SplFileObject class.
     * Since this class exhibits a reported bug (https://bugs.php.net/bug.php?id=55413),
     * Data using the escape character are correctly
     * escaped but the escape character is not removed from the CSV content.
     */
    'escape' => '\\',

    'newline' => "\n",

    /*
    |--------------------------------------------------------------------------
    | Progress bar messages
    |-------------------------------------------------------------------------- 
    */

    'does_not_running' => 'Import process does not run',
    'initialization'   => 'Initialization',
    'progress'         => 'Import process is running',
    'final_stage'      => 'Final stage',
    'finished'         => 'Almost done, please click to the `finish` button to proceed',
    'final'            => 'The import process successfully finished!'

];
