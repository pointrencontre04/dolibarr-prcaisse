<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (isModEnabled('prbonalim')) {
    require_once DOL_DOCUMENT_ROOT . '/custom/prbonalim/class/bonalim.class.php';
}

$langs->load("admin");
$langs->load("companies");

// Sécurité : l'utilisateur doit être connecté
if (empty($user->id)) {
    http_response_code(403);
    exit('Accès refusé : utilisateur non connecté.');
}

if (!$user->hasRight('takepos', 'run')) {
    accessforbidden('Access to TakePOS forbidden');
}

if (!$user->rights->facture->lire) {
    accessforbidden('Access to Facture forbidden');
}

/*
// Vérifier qu’un client est sélectionné
if (empty($_SESSION['takepos_customer_id'])) {
    http_response_code(400);
    exit('Client non sélectionné');
}
*/

// Retrieve Facture object to handle
$invoice_id = GETPOST('invoice_id', 'int');
$invoice = new Facture($db);
$res = $invoice->fetch($invoice_id);
if ($res <= 0) {
    http_response_code(404);
    exit('Facture invalide.');
}

$thirdparty_id = $invoice->socid;

if (empty($thirdparty_id) || $thirdparty_id <= 0) {
    http_response_code(400);
    exit('Paramètre manquant ou invalide.');
}

$pos_source = (int) $_SESSION['takeposterminal'];

// Exclude any Generic user from this script
$numberofterminals = getDolGlobalString('TAKEPOS_NUM_TERMINALS', '1');
for ($terminal = 1; $terminal <= $numberofterminals; $terminal++) {
    $is_thirdparty_generic = ($thirdparty_id == getDolGlobalInt('CASHDESK_ID_THIRDPARTY'.$terminal));
    if ($is_thirdparty_generic) {
        exit;
    }
}

// Récupération du tiers
$thirdparty = new Societe($db);
$res = $thirdparty->fetch($thirdparty_id);
if ($res <= 0) {
    http_response_code(404);
    exit('Client introuvable.');
}

// Autorisation : l'utilisateur doit être admin ou avoir droit de voir ce tiers
$authorized = false;

if ($user->admin) {
    $authorized = true;
} elseif ($user->rights->societe->lire) {
    // Gestion commerciale activée ?
    if (!empty($conf->global->SOCIETE_USE_COMMERCIAL_CONTACTS)) {
        // L'utilisateur est-il commercial de ce tiers ?
        if ($thirdparty->isACompanyOfUser($user->id)) {
            $authorized = true;
        }
    } else {
        // Pas de restriction commerciale → accès global autorisé
        $authorized = true;
    }
}

if (!$authorized) {
    accessforbidden('You are not permitted to load user details');
}

$cancreatediscount = ($user->hasRight('societe', 'creer') || $user->hasRight('facture', 'creer'));
if (!$cancreatediscount) {
    accessforbidden('You are not permitted to change discounts');
}

$results = [];

$amountpayed = GETPOST('amount', 'int');

if ($amountpayed <= 0) {
    $amountpayed = $invoice->getRemainToPay();
} elseif ($amountpayed > $invoice->getRemainToPay()) {
    $amountpayed = $invoice->getRemainToPay();
}

$totalavailablediscounts = $thirdparty->getAvailableDiscounts();
$remaintopay = $invoice->getRemainToPay();

// List available discounts

$sql = "SELECT rc.rowid, rc.amount_ht, rc.amount_tva, rc.amount_ttc, rc.tva_tx, rc.vat_src_code,";
$sql .= " rc.multicurrency_amount_ht, rc.multicurrency_amount_tva, rc.multicurrency_amount_ttc,";
$sql .= " rc.datec as dc, rc.description,";
$sql .= " rc.fk_facture_source,";
$sql .= " u.login, u.rowid as user_id, u.statut as status, u.firstname, u.lastname, u.photo,";
$sql .= " fa.ref as ref, fa.type as type";
$sql .= " FROM  ".MAIN_DB_PREFIX."user as u, ".MAIN_DB_PREFIX."societe_remise_except as rc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture as fa ON rc.fk_facture_source = fa.rowid";
$sql .= " WHERE rc.fk_soc = ".((int) $thirdparty->id);
$sql .= " AND rc.entity = ".((int) $conf->entity);
$sql .= " AND u.rowid = rc.fk_user";
$sql .= " AND rc.discount_type = 0"; // Eliminate supplier discounts
$sql .= " AND (rc.fk_facture_line IS NULL AND rc.fk_facture IS NULL)";
$sql .= " ORDER BY rc.datec ASC";

$resql = $db->query($sql);

$num = $db->num_rows($resql);
if ($num > 0) {
    $db->begin();
    $i = 0;
    while ($i < $num && $remaintopay > 0) {
        $obj = $db->fetch_object($resql);
        $discountamount = $obj->amount_ttc;

        if ($discountamount <= $remaintopay) {
            // Apply discount
            $result = $invoice->insert_discount($obj->rowid);
            echo 'Applying discount ' . $obj->rowid . ' for invoice ' . $invoice->id;

            if ($result < 0) {
                $db->rollback();
                exit($invoice->error);
            }

            //$remaintopay = $remaintopay - $discountamount;
            $remaintopay = $invoice->getRemainToPay();

            if ($remaintopay <= 0) {
                $invoice->validate($user);
                $invoice->setPaid($user);
            }

        } else {
            // Split amount, then apply discount and close invoice

            // Split amount
            $amount_ttc_1 = $amountpayed;
            $amount_ttc_2 = $discountamount - $amountpayed;

            $error = 0;
            $remid = $obj->rowid;
            $discount = new DiscountAbsolute($db);
            $res = $discount->fetch($remid);
            if (!($res > 0)) {
                $error++;
                setEventMessages($langs->trans("ErrorFailedToLoadDiscount"), null, 'errors');
            }
            if (!$error && price2num((float) $amount_ttc_1 + (float) $amount_ttc_2) != $discount->amount_ttc) {
                $error++;
                setEventMessages($langs->trans("TotalOfTwoDiscountMustEqualsOriginal"), null, 'errors');
            }
            if (!$error && $discount->fk_facture_line) {
                $error++;
                setEventMessages($langs->trans("ErrorCantSplitAUsedDiscount"), null, 'errors');
            }
            if (!$error) {
                $newdiscount1 = new DiscountAbsolute($db);
                $newdiscount2 = new DiscountAbsolute($db);
                $newdiscount1->fk_facture_source = $discount->fk_facture_source;
                $newdiscount2->fk_facture_source = $discount->fk_facture_source;
                $newdiscount1->fk_facture = $discount->fk_facture;
                $newdiscount2->fk_facture = $discount->fk_facture;
                $newdiscount1->fk_facture_line = $discount->fk_facture_line;
                $newdiscount2->fk_facture_line = $discount->fk_facture_line;
                $newdiscount1->fk_invoice_supplier_source = $discount->fk_invoice_supplier_source;
                $newdiscount2->fk_invoice_supplier_source = $discount->fk_invoice_supplier_source;
                $newdiscount1->fk_invoice_supplier = $discount->fk_invoice_supplier;
                $newdiscount2->fk_invoice_supplier = $discount->fk_invoice_supplier;
                $newdiscount1->fk_invoice_supplier_line = $discount->fk_invoice_supplier_line;
                $newdiscount2->fk_invoice_supplier_line = $discount->fk_invoice_supplier_line;
                if ($discount->description == '(CREDIT_NOTE)' || $discount->description == '(DEPOSIT)') {
                    $newdiscount1->description = $discount->description;
                    $newdiscount2->description = $discount->description;
                } else {
                    $newdiscount1->description = $discount->description.' (1)';
                    $newdiscount2->description = $discount->description.' (2)';
                }

                $newdiscount1->fk_user = $discount->fk_user;
                $newdiscount2->fk_user = $discount->fk_user;
                $newdiscount1->fk_soc = $discount->fk_soc;
                $newdiscount1->socid = $discount->socid;
                $newdiscount2->fk_soc = $discount->fk_soc;
                $newdiscount2->socid = $discount->socid;
                $newdiscount1->discount_type = $discount->discount_type;
                $newdiscount2->discount_type = $discount->discount_type;
                $newdiscount1->datec = $discount->datec;
                $newdiscount2->datec = $discount->datec;
                $newdiscount1->tva_tx = $discount->tva_tx;
                $newdiscount2->tva_tx = $discount->tva_tx;
                $newdiscount1->vat_src_code = $discount->vat_src_code;
                $newdiscount2->vat_src_code = $discount->vat_src_code;
                $newdiscount1->amount_ttc = $amount_ttc_1;
                $newdiscount2->amount_ttc = price2num($discount->amount_ttc - $newdiscount1->amount_ttc);
                $newdiscount1->amount_ht = price2num($newdiscount1->amount_ttc / (1 + $newdiscount1->tva_tx / 100), 'MT');
                $newdiscount2->amount_ht = price2num($newdiscount2->amount_ttc / (1 + $newdiscount2->tva_tx / 100), 'MT');
                $newdiscount1->amount_tva = price2num($newdiscount1->amount_ttc - $newdiscount1->amount_ht);
                $newdiscount2->amount_tva = price2num($newdiscount2->amount_ttc - $newdiscount2->amount_ht);

                $newdiscount1->multicurrency_amount_ttc = (float) $amount_ttc_1 * ($discount->multicurrency_amount_ttc / $discount->amount_ttc);
                $newdiscount2->multicurrency_amount_ttc = price2num($discount->multicurrency_amount_ttc - $newdiscount1->multicurrency_amount_ttc);
                $newdiscount1->multicurrency_amount_ht = price2num($newdiscount1->multicurrency_amount_ttc / (1 + $newdiscount1->tva_tx / 100), 'MT');
                $newdiscount2->multicurrency_amount_ht = price2num($newdiscount2->multicurrency_amount_ttc / (1 + $newdiscount2->tva_tx / 100), 'MT');
                $newdiscount1->multicurrency_amount_tva = price2num($newdiscount1->multicurrency_amount_ttc - $newdiscount1->multicurrency_amount_ht);
                $newdiscount2->multicurrency_amount_tva = price2num($newdiscount2->multicurrency_amount_ttc - $newdiscount2->multicurrency_amount_ht);

                $discount->fk_facture_source = 0; // This is to delete only the require record (that we will recreate with two records) and not all family with same fk_facture_source
                // This is to delete only the require record (that we will recreate with two records) and not all family with same fk_invoice_supplier_source
                $discount->fk_invoice_supplier_source = 0;
                $res = $discount->delete($user);
                $newid1 = $newdiscount1->create($user);
                $newid2 = $newdiscount2->create($user);
                if (!($res > 0 && $newid1 > 0 && $newid2 > 0)) {
                    $db->rollback();
                    exit;
                }
            }

            // Apply discount
            $result = $invoice->insert_discount($newid1);
            echo 'Applying discount ' . $newid1 . ' for invoice ' . $invoice->id;

            if ($result < 0) {
                $db->rollback();
                exit($invoice->error);
            }

            $remaintopay = $invoice->getRemainToPay(); // Should be 0

            // Close invoice
            if ($remaintopay <= 0) {
                $invoice->validate($user);
                $invoice->setPaid($user);
            }
        }

        $i++;
    }
    $db->commit();
}
