<?php

use App\Core\Security;

// Security check  
Security::ensureSecure();

// No need for autoloader - it's already loaded in index.php

function testing(){
    return 'testing 123';
}



function testing2(){
    // pp and ppp work here too - they're global functions
    pp("Debug from helper function");
    
    $result = 'testing 123';
    ppp($result);
    
    return $result;
}