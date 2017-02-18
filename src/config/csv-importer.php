<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Main csv import configuration
    |-------------------------------------------------------------------------- 
    |
    | `mutex_lock_time` - how long script will be executed and how long the import process will be locked,
    |   another words if we will import list of electric guitars we won't be able to run another import of electric
    |   guitars at the same time, to avoid duplicates and different sorts of incompatibilities. The value set in minutes.
    |
    | `memory_limit` - if you want store all csv values in memory or something like that,
    |   you may increase amount of memory for the script
    |
    | `import_lock_key` - global import key which is basically `primary key of import`, from the key will be created
    |   all others cache keys for the import, by concatenation:
    |
    |   'import_lock_key' . '_paths'
    |   'import_lock_key' . '_quantity'
    |   'import_lock_key' . '_processed'
    |   'import_lock_key' . '_message'
    |   'import_lock_key' . '_cancel'
    |   'import_lock_key' . '_details'
    |   'import_lock_key' . '_finished'
    |
    |   By default `import_lock_key` set to the import class name `static::class`
    |
    | `encoding` - which encoding we have, UTF-8 by default  
    |
    */

    'mutex_lock_time' => 300,
    
    'memory_limit' => 128,
    
    'import_lock_key' => null, // if it set to `false` (null, 0, false, '') value then it will be `static::class`

    /*
     * Encoding of given csv file
     */
    'input_encoding' => 'UTF-8',

    /*
     * Encoding of processed csv values
     */
    'output_encoding' => 'UTF-8',

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

);
