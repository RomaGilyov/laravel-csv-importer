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
    | `artifact` - array of values which will be truncated from every string value from given csv
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
    
    'memory_limit' => 256,
    
    'artifacts' => [],
    
    'import_lock_key' => null, // if it set to `false` (null, 0, false, '') value then it will be `static::class`
    
    'encoding' => 'UTF-8',

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
