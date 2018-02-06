<?php

// sets up stellar network connection + keypair variables
require __DIR__ . '/common.php';

/*
 * Fund worker and customer accounts
 */
$stellarNetwork->fundAccount($workerKeypair);
$stellarNetwork->fundAccount($customerKeypair);

print "Worker  : " . $workerKeypair->getPublicKey() . PHP_EOL;
print "Customer: " . $customerKeypair->getPublicKey() . PHP_EOL;
print "Escrow  : " . $escrowKeypair->getPublicKey() . PHP_EOL;
print "Vanity  : " . $vanityKeypair->getPublicKey() . PHP_EOL;