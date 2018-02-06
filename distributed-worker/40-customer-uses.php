<?php
// sets up stellar network connection + keypair variables
require __DIR__ . '/common.php';

use \ZuluCrypto\StellarSdk\Keypair;
use ZuluCrypto\StellarSdk\XdrModel\SignerKey;
use ZuluCrypto\StellarSdk\XdrModel\Signer;
use ZuluCrypto\StellarSdk\XdrModel\Operation\SetOptionsOp;

/*
 * Now that the vanity keypair has been transferred over, the master key is
 * no longer able to sign any transactions on the account.
 *
 * Instead, the customer will set the source account to $vanityKeypair and sign
 * with their own account.
 */

// Send some XLM to the vanity account
print "[1 of 2] Sending XLM from customer -> vanity...";
$stellarNetwork->buildTransaction($customerKeypair)
    ->addLumenPayment($vanityKeypair, 10.00001) // includes fee for return transaction
    ->submit($customerKeypair);
print "DONE" . PHP_EOL;



// Send XLM back from the vanity account
// NOTE: signer on this is still $customerKeypair
print "[2 of 2] Returning XLM to customer...";
$stellarNetwork->buildTransaction($vanityKeypair)
    ->addLumenPayment($customerKeypair, 10)
    ->submit($customerKeypair);
print "DONE" . PHP_EOL;