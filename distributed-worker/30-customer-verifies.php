<?php
// sets up stellar network connection + keypair variables
require __DIR__ . '/common.php';

use \ZuluCrypto\StellarSdk\Keypair;
use ZuluCrypto\StellarSdk\XdrModel\SignerKey;
use ZuluCrypto\StellarSdk\XdrModel\Signer;
use ZuluCrypto\StellarSdk\XdrModel\Operation\SetOptionsOp;

/*
 * In order to know the status of their order, the customer can query the data
 * properties of the worker account. Since there could be several vanity keypairs
 * being generated for multiple customers, the worker will set data flags that
 * start with the customer's public key.
 *
 * The three keys we'll look for are:
 *
 *  - Order status (no suffix)
 *  - Proof that the worker has really created the keypair (_proof)
 *  - The worker's signature that will allow us to submit the final transaction (_sig)
 */

$workerAccount = $stellarNetwork->getAccount($workerKeypair->getPublicKey());

$accountData = $workerAccount->getData();

// If the customer keypair doesn't show up, the worker hasn't discovered the request yet
if (!isset($accountData[$customerKeypair->getPublicKey()])) {
    die("Worker has not started generating the vanity address");
}

// Worker has started work on generating the vanity keypair
if ($accountData[$customerKeypair->getPublicKey()] == "PROCESSING") {
    die("Worker has not generated the vanity keypair yet");
}

// At this point, the worker is finished and we can read the data we'll need for
// closing the transaction by paying the worker and receiving the vanity account
$vanityPublicKey = $accountData[$customerKeypair->getPublicKey()];
$workerProofSignature = $accountData[$customerKeypair->getPublicKey() . '_proof'];
$workerTransactionSignature = $accountData[$customerKeypair->getPublicKey() . '_sigW'];
$vanityTransactionSignature = $accountData[$customerKeypair->getPublicKey() . '_sigV'];

print "Worker found vanity key: " . $vanityPublicKey . PHP_EOL;

// Verify that the signature is valid by checking that the worker was able to sign
// a message with the private key of the vanity keypair
$verificationKeypair = Keypair::newFromPublicKey($vanityPublicKey);

$isValid = $verificationKeypair->verifySignature($workerProofSignature, 'vanity address confirmation');

if ($isValid) {
    print "Signature valid: worker has the private key for $vanityPublicKey" . PHP_EOL;
}
else {
    die("Invalid signature, cannot confirm $vanityPublicKey");
}

/*
 * Everything checks out, so now it's time to submit the finalizing transaction
 * to the network.
 *
 * This transaction:
 *
 * 1. Clears out account data (cannot merge an account that has data values)
 * 2. Merges the escrow account into the worker account (paying for the key)
 * 3. Vanity account: Adds the customer's account as a signer with weight 1
 * 4. Vanity account: Sets all thresholds to 1
 * 5. Vanity account: Sets the master weight to 0 (now it cannot be used by the worker)
 *
 * This transaction will require two signatures (one by the customer and one by
 * the worker). The worker has already published their signature as a data value,
 * so it's up to the customer to generate a signature and submit to the network.
 */

// Generate transaction
$signerKey = SignerKey::fromKeypair($customerKeypair);
$signer = new Signer($signerKey, 1);

// NOTE: source account on this operation is the vanity keypair account.
// The source account for all other operations is the escrow keypair
$op = new SetOptionsOp($vanityPublicKey);
$op->updateSigner($signer);
$op->setMasterWeight(0);
$op->setLowThreshold(1);
$op->setMediumThreshold(1);
$op->setHighThreshold(1);

// NOTE: this transaction must exactly match the one built in 20-worker-generates.php
$finalizeTx = $stellarNetwork->buildTransaction($customerKeypair)
    // Must be cleared to merge account
    ->clearAccountData('request:generateVanityAddress', $escrowKeypair)
    ->addOperation($op)
    ->addMergeOperation($workerKeypair, $escrowKeypair)
    ->sign($customerKeypair);

// Add the signature from the worker that we read from the data value on their
// account. We need the worker's public key for calculating the signature's hint
$finalizeTx->addRawSignature($workerTransactionSignature, Keypair::newFromPublicKey($workerKeypair->getPublicKey()));

// Add the signature for the vanity keypair since it's required by the operation
// that adds the customer as a signer and updates the weights
$finalizeTx->addRawSignature($vanityTransactionSignature, Keypair::newFromPublicKey($vanityPublicKey));

print "Submitting transfer transaction...";
$stellarNetwork->submitB64Transaction($finalizeTx->toBase64());
print "DONE" . PHP_EOL;