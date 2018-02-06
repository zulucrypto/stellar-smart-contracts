<?php
// sets up stellar network connection + keypair variables
require __DIR__ . '/common.php';

use ZuluCrypto\StellarSdk\XdrModel\SignerKey;
use ZuluCrypto\StellarSdk\XdrModel\Signer;
use ZuluCrypto\StellarSdk\XdrModel\Operation\SetOptionsOp;

/*
 * This file demonstrates what the worker does to handle a user's request.
 *
 * The assumption is that before this script is run:
 *
 * 1. There's a process monitoring the Stellar network for accounts setting a
 *  "request:generateVanityAddress" data value
 *
 * 2. The monitoring process verifies that the account is locked and can't be
 *  modified by anyone while the worker is running
 *
 * 3. The customer account has enough funds to make searching for the vanity
 *  address worth it
 *
 * Then, the monitoring script would start this script
 */

// ----------------------------------------------------

// In order to let the customer know that we've found their request and are working
// on it, we set a key/value pair with their public address and the current status

//$stellarNetwork->buildTransaction($workerKeypair)
//    ->setAccountData($customerKeypair->getPublicKey(), 'PROCESSING')
//    ->submit($workerKeypair);


// ----------------------------------------------------
// todo: in a real implementation, this is where the processing would happen
// Since this is just a demo, we already have the key generated and stored
// in $vanityKeypair
print "[SIMULATED] Found vanity keypair: " . $vanityKeypair->getPublicKey() . PHP_EOL;


// After the account is discovered, fund it with the minimum balance
// min balance + 1 extra signer (customer)
$newAccountMinBalance = 1 + 0.5;

// Fund the vanity keypair so we can add the customer as a signer
if (!$stellarNetwork->getAccount($vanityKeypair)) {
    print "Funding vanity keypair from " . $workerKeypair->getPublicKey() . " ";
    $stellarNetwork->buildTransaction($workerKeypair)
        ->addCreateAccountOp($vanityKeypair, $newAccountMinBalance)
        ->submit($workerKeypair);
    print "DONE" . PHP_EOL;
}

// ----------------------------------------------------
/*
 * Once the keypair has been found, we need to let the customer see it as well
 * as prove that we really know the secret key and didn't just make up a public
 * key.
 */

// Showing the generated keypair to the customer is as easy as updating the
// key/value pair

$stellarNetwork->buildTransaction($workerKeypair)
    ->setAccountData($customerKeypair->getPublicKey(), $vanityKeypair->getPublicKey())
    ->submit($workerKeypair);

// Proving that we have the secret key is a matter of signing something with it
// so that the customer can verify we actually control it. In this case, we'll
// just sign the string "vanity address confirmation"

$message = "vanity address confirmation";
$signature = $vanityKeypair->sign($message);

// Write this to the blockchain as well so it can be verified
$stellarNetwork->buildTransaction($workerKeypair)
    ->setAccountData($customerKeypair->getPublicKey() . "_proof", $signature)
    ->submit($workerKeypair);


// Finally, generate the transaction that will be used to transfer the vanity
// address and pay the worker
$signerKey = SignerKey::fromKeypair($customerKeypair);
$signer = new Signer($signerKey, 1);

// NOTE: source account on this operation is the vanity keypair account.
// The source account for all other operations is the escrow keypair
$op = new SetOptionsOp($vanityKeypair);
$op->updateSigner($signer);
$op->setMasterWeight(0);
$op->setLowThreshold(1);
$op->setMediumThreshold(1);
$op->setHighThreshold(1);

// NOTE: this transaction must exactly match the one built in 30-customer-verifies.php
$finalizeTx = $stellarNetwork->buildTransaction($customerKeypair)
    // Must be cleared to merge account
    ->clearAccountData('request:generateVanityAddress', $escrowKeypair)
    ->addOperation($op)
    ->addMergeOperation($workerKeypair, $escrowKeypair);

// Publish the worker's signature for this transaction
$stellarNetwork->buildTransaction($workerKeypair)
    ->setAccountData($customerKeypair->getPublicKey() . "_sigW", $finalizeTx->getSignatureForKeypair($workerKeypair)->getRawSignature())
    ->submit($workerKeypair);

// Publish the vanity account's signature (required for updating its signers)
$stellarNetwork->buildTransaction($workerKeypair)
    ->setAccountData($customerKeypair->getPublicKey() . "_sigV", $finalizeTx->getSignatureForKeypair($vanityKeypair)->getRawSignature())
    ->submit($workerKeypair);


// See 30-customer-verifies.php for how the customer verifies the proof, constructs
// the same transaction, adds their signature, and submits

print "Worker Account: " . $workerKeypair->getPublicKey() . PHP_EOL;
print "Updated Worker account with proof of keypair" . PHP_EOL;