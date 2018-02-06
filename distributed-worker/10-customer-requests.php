<?php
// sets up stellar network connection + keypair variables
require __DIR__ . '/common.php';

use \phpseclib\Math\BigInteger;
use \ZuluCrypto\StellarSdk\XdrModel\SignerKey;
use \ZuluCrypto\StellarSdk\XdrModel\Signer;
use \ZuluCrypto\StellarSdk\XdrModel\Operation\SetOptionsOp;

/**
 * This file sets up the customer's request for the worker to generate a new
 * vanity keypair
 */

/*
 * First, the customer decides how much they're willing to pay and then funds
 * an escrow account.
 */

// todo: real world implementation would generate $escrowKeypair randomly
// in this example, it comes from common.php
if (!$stellarNetwork->getAccount($escrowKeypair)) {
    $stellarNetwork->buildTransaction($customerKeypair)
        ->addCreateAccountOp($escrowKeypair, 100.00006) // 100 XLM after setup fees + transfer transaction
        ->submit($customerKeypair);
}

print "Created escrow account: " . $escrowKeypair->getPublicKey() . PHP_EOL;

/*
 * In order to make this an escrow account, we need to prove to the worker that
 * no one is able to withdraw funds from it while the worker is searching for
 * a vanity address.
 *
 * This is accomplished by:
 *  - Making the worker and customer signers of equal weight (1)
 *  - Requiring both signers to agree on any transaction (thresholds are set to 2)
 *
 * However, we also need to handle the case where no worker takes the job and we
 * need to reclaim the account. This can be done by adding a preauthorized merge
 * transaction that's not valid until 30 days from now.
 *
 * This allows the worker to know that the funds are guaranteed to be available
 * for 30 days.
 */

// Load up the escrow account
$account = $stellarNetwork->getAccount($escrowKeypair);

// Precalculate some sequence numbers since they're necessary for transactions
$startingSequenceNumber = $account->getSequence();
// Track how many transactions are necessary to set up the escrow account
// We need this so we can correctly calculate the "reclaim account" sequence number
$numSetupTransactions = 5;

$reclaimAccountOrPaySeqNum = $startingSequenceNumber + $numSetupTransactions + 1;

// Update the account with a data value indicating what vanity address to search for
print "Adding data entry to request a vanity address...";
$stellarNetwork->buildTransaction($escrowKeypair)
    ->setAccountData('request:generateVanityAddress', 'G*ZULU')
    ->submit($escrowKeypair);
print "DONE" . PHP_EOL;

// Fallback transaction: reclaim the escrow account if no workers generate the
// vanity address in 30 days
$reclaimTx = $stellarNetwork->buildTransaction($escrowKeypair)
    ->setSequenceNumber(new BigInteger($reclaimAccountOrPaySeqNum))
    // todo: uncomment this out in a real implementation
    //->setLowerTimebound(new \DateTime('+30 days'))
    ->setAccountData('request:generateVanityAddress')
    ->addMergeOperation($customerKeypair)
    ->getTransactionEnvelope();

// Add hash of $reclaimTx as a signer on the account
// See: https://www.stellar.org/developers/guides/concepts/multi-sig.html#pre-authorized-transaction
$txHashSigner = new Signer(
    SignerKey::fromPreauthorizedHash($reclaimTx->getHash()),
    2 // weight must be enough so no other signers are needed
);
$addReclaimTxSignerOp = new SetOptionsOp();
$addReclaimTxSignerOp->updateSigner($txHashSigner);

print "Adding pre-authorized reclaim transaction as a signer... ";
$stellarNetwork->buildTransaction($escrowKeypair)
    ->addOperation($addReclaimTxSignerOp)
    ->submit($escrowKeypair);
print "DONE" . PHP_EOL;

print "Added pre-auth reclaim transaction valid at sequence " . $reclaimAccountOrPaySeqNum . PHP_EOL;
print "To reclaim the escrow account, run 90-reclaim-escrow.php" . PHP_EOL;

// Add worker account as a signer of weight 1
$workerSigner = new Signer(
    SignerKey::fromKeypair($workerKeypair),
    1 // requires another signer
);
$addSignerOp = new SetOptionsOp();
$addSignerOp->updateSigner($workerSigner);
$stellarNetwork->buildTransaction($escrowKeypair)
    ->addOperation($addSignerOp)
    ->submit($escrowKeypair);

// Add customer account as second signer of weight 1
$workerSigner = new Signer(
    SignerKey::fromKeypair($customerKeypair),
    1 // requires another signer
);
$addSignerOp = new SetOptionsOp();
$addSignerOp->updateSigner($workerSigner);
$stellarNetwork->buildTransaction($escrowKeypair)
    ->addOperation($addSignerOp)
    ->submit($escrowKeypair);

// Increase thresholds and set master weight to 0
// All operations now require threshold of 2

$thresholdsOp = new SetOptionsOp();
$thresholdsOp->setLowThreshold(2);
$thresholdsOp->setMediumThreshold(2);
$thresholdsOp->setHighThreshold(2);
$thresholdsOp->setMasterWeight(0);
$stellarNetwork->buildTransaction($escrowKeypair)
    ->addOperation($thresholdsOp)
    ->submit($escrowKeypair);

print PHP_EOL;
print "Finished configuring escrow account" . PHP_EOL;