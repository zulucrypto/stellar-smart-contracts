<?php

// sets up stellar network connection + keypair variables
require __DIR__ . '/common.php';


/*
 * If nothing happens by the time the pre-authorized merge transaction becomes
 * valid, the customer can reclaim their funds.
 */

// Note that no signer is necessary since the transaction has already been authorized
$stellarNetwork->buildTransaction($escrowKeypair)
    // Must clean up data to be able to merge account
    ->setAccountData('request:generateVanityAddress')
    ->addMergeOperation($customerKeypair)
    ->submit();