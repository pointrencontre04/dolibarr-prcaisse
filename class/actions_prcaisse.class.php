<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2025		SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    prcaisse/class/actions_prcaisse.class.php
 * \ingroup prcaisse
 * \brief   Example hook overload.
 *
 * TODO: Write detailed description here.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

if (isModEnabled('prbonalim')) {
    require_once DOL_DOCUMENT_ROOT . '/custom/prbonalim/core/modules/modPRBonAlim.class.php';
    require_once DOL_DOCUMENT_ROOT . '/custom/prbonalim/class/bonalim.class.php';
}

/**
 * Class ActionsPRCaisse
 */
class ActionsPRCaisse extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();


	/**
	 * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Execute action completeTakePosInvoiceHeader
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function completeTakePosInvoiceHeader(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!in_array('takeposinvoice', $hookmanager->contextarray)) {
			return 0;
		}

		/*
		$out = '
		$(document).ready(function() {
			$("#topnav-left").append(\'<div id="client_encours" style="font-weight:bold; margin-top:10px;"></div>\');

			$(document).on("change", "#customer_id", function() {
				var id = $(this).val();
				if (id) {
					$.get("' . $module_path . '?id=" + id, function(data) {
						$("#client_encours").text("Encours : " + data + " €");
					});
				} else {
					$("#client_encours").text("");
				}
			});
		});
		';

		$this->results = $out;
		$this->resprints = $out;
		*/

		$resprints = '';

		$invoice_id = $object->id;

		/**
		 * Add JavaScript callback to show
		 * customer informations on left pane
		 */
		$callback_url = dol_buildpath('/prcaisse/callbacks/get_customer_infos.php', 1);
		ob_start();
		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {

				if (jQuery('#customer_infos').length == 0) {
					jQuery('#customerandsales').append('<div id="customer_infos"></div>');
				}

				const params = {
					invoice_id: <?php echo $invoice_id; ?>,
					token: "<?php echo newToken(); ?>"
				};

				jQuery.ajax({
					url: '<?php echo $callback_url; ?>',
					method: 'GET',
					data: params,
					success: function(data) {
						$('#customer_infos').html(data);
					},
				});
			});
		</script>
		<?php
		$resprints .= ob_get_clean();

		if ($object->type == Facture::TYPE_DEPOSIT && $object->status == Facture::STATUS_DRAFT) {
			ob_start();
			?>
			<div class="invoice_header_info info_deposit">
				<div class="title"><?php echo $langs->trans('InvoiceDeposit') ?></div>
				<div class="legend">En encaissant cette facture, le montant sera converti en Avance dans le compte client.</div>
			</div>
			<?php
			$resprints .= ob_get_clean();
		}

		if ($object->type == Facture::TYPE_DEPOSIT && ($object->status == Facture::STATUS_VALIDATED || $object->status == Facture::STATUS_CLOSED)) {
			ob_start();
			?>
			<div class="invoice_header_info info_deposit">
				<div class="title"><?php echo $langs->trans('InvoiceDeposit') ?></div>
				<div class="legend">Cette facture a été convertie en Avance dans le compte client.</div>
			</div>
			<?php
			$resprints .= ob_get_clean();
		}


		$this->resprints = $resprints;

		return 0;
	}

	/**
	 * Execute action ActionButtons
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function ActionButtons(&$parameters, &$object, &$action, $hookmanager)
	{
		if (!in_array('takeposfrontend', $hookmanager->contextarray)) {
			return 0;
		}

		// Replace the Split button with a button to
		// convert the invoice to a TYPE_DEPOSIT

		$results = $parameters['menus'];

		foreach ($results as $key => $value) {
			if ($value['action'] == 'Split();') {
				$results[$key]['title'] = '<span class="fa fa-money-check-alt paddingrightonly"></span><div class="trunc">Convertir en acompte</div>';
				$results[$key]['action'] = 'PRCaisseSwitchInvoiceType();';
			}
		}

		$this->results[] = $results;

		return 1;
	}

	/**
	 * Execute action addMoreActionsButtons
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function addMoreActionsButtons(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user, $db;

		if (!in_array('takepospay', $hookmanager->contextarray)) {
			return 0;
		}

		if (!$object->socid) {
			return 0;
		} else {
			$thirdparty_id = $object->socid;
			$thirdparty = new Societe($db);
			if (!$thirdparty->fetch($thirdparty_id)) {
				return 0;
			}
		}

		/**
		 * Add actions to pay with BonAlim
		 */
		if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'write')) {

			// Check if owner of invoice has any BonAlim available
			$bonalim = new BonAlim($db);
			$bonalim_list = $bonalim->fetchAll(
				'',
				'',
				0,
				0,
				'(beneficiary:=:' . $thirdparty_id . ') AND (status:=:' . BonAlim::STATUS_CREDITED . ')'
			);
			if ($bonalim_list) {
				$bonsalim_total = 0;
				foreach ($bonalim_list as $b) {
					$bonsalim_total += $b->amount_left;
				}
			} else {
				$bonsalim_total = 0;
			}

			if ($bonsalim_total > 0) {
				//var_dump($parameters['action_buttons']);
				$this->results = $parameters['action_buttons'];
				array_unshift($this->results, [
					'class' => '',
					'function' => 'ValidateBonAlim()',
					'span' => '',
					'text' => '<span class="fa fa-ticket-alt"></span></span><br>Bons Alimentaires<span>',
				]);
				return 1;
			}
		}

		/**
		 * Check if owner of invoice has any discount available
		 */
		if ($thirdparty->getAvailableDiscounts() > 0) {
			$this->results = $parameters['action_buttons'];
			array_unshift($this->results, [
				'class' => '',
				'function' => 'ValidateAddDiscount()',
				'span' => '',
				'text' => '<span class="fa fa-ticket-alt"></span></span><br>Appliquer les acomptes<span>',
			]);
		}

		return 0;
	}

	/**
	 * Execute action completePayment
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function completePayment(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user, $db;

		if (!in_array('takepospay', $hookmanager->contextarray)) {
			return 0;
		}

		$resprints = '';

		$invoice = $object;
		$invoice_id = $object->id;
		$invoice_customer_id = $object->socid;

		$remaintopay = 0;
		if ($invoice->id > 0) {
			$remaintopay = $invoice->getRemainToPay();
		}
		//$alreadypayed = (is_object($invoice) ? ($invoice->total_ttc - $remaintopay) : 0);

		$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : '0'); // $place is id of table for Bar or Restaurant

		/**
		 * Add JavaSCript callback to process
		 * the payments with BONALI method
		 */
		if (isModEnabled('prbonalim') && $user->hasRight('bonalim@prbonalim', 'write')) {
			ob_start();
			?>
			<script type="text/javascript">
				function ValidateBonAlim() {
                	console.log("Launch ValidateBonAlim");

					var invoiceid = <?php echo($invoice_id > 0 ? $invoice_id : 0); ?>;
					var accountid = $("#selectaccountid").val();
					var amountpayed = $("#change1").val();
					var excess = $("#change2").val();
					if (amountpayed > <?php echo $invoice->total_ttc; ?>) {
						amountpayed = <?php echo $invoice->total_ttc; ?>;
					}
					console.log("We click on the payment mode to pay amount = "+amountpayed);
					parent.$("#poslines").load("invoice.php?place=<?php echo $place; ?>&action=valid&token=<?php echo newToken(); ?>&pay=<?php echo modPRBonAlim::BONALIM_PAYMENT_CODE; ?>&amount="+amountpayed+"&excess="+excess+"&invoiceid="+invoiceid+"&accountid="+accountid, function() {
						if (amountpayed > <?php echo $remaintopay; ?> || amountpayed == <?php echo $remaintopay; ?> || amountpayed==0 ) {
							console.log("Close popup");
							parent.$.colorbox.close();
						}
						else {
							console.log("Amount is not complete, so we do NOT close popup and reload it.");
							location.reload();
						}
					});

					return true;
		        }

				function ValidateAddDiscount() {
                	console.log("Launch ValidateAddDiscount");

					var invoiceid = <?php echo($invoice_id > 0 ? $invoice_id : 0); ?>;
					var accountid = $("#selectaccountid").val();
					var amountpayed = $("#change1").val();
					var excess = $("#change2").val();
					if (amountpayed > <?php echo $invoice->total_ttc; ?>) {
						amountpayed = <?php echo $invoice->total_ttc; ?>;
					}
					console.log("We click on the payment mode to pay amount = "+amountpayed);
					const callback_url = "<?php echo dol_buildpath('/prcaisse/callbacks/apply_discounts.php', 1); ?>";
					const params = {
						place: <?php echo $place; ?>,
						invoice_id: invoiceid,
						amount: amountpayed,
						excess: excess,
						accountid: accountid,
						token: "<?php echo newToken(); ?>"
					};
					jQuery.ajax({
						url: callback_url
						method: 'POST',
						data: params,
						success: function(data) {
							if (amountpayed > <?php echo $remaintopay; ?> || amountpayed == <?php echo $remaintopay; ?> || amountpayed==0 ) {
								console.log("Close popup");
								parent.$.colorbox.close();
							}
							else {
								console.log("Amount is not complete, so we do NOT close popup and reload it.");
								location.reload();
							}
						},
					});

					return true;
		        }
			</script>
			<?php
			$resprints .= ob_get_clean();
		}

		$this->resprints = $resprints;

		return 0;
	}

	/**
	 * Execute action doActions
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function doActions(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user, $db;

		$pay = GETPOST('pay', 'aZ09');
		$amountofpayment = GETPOSTFLOAT('amount');

		if (!in_array('takeposinvoice', $hookmanager->contextarray)) {
			return 0;
		}

		if ($action != 'valid' || $pay != modPRBonAlim::BONALIM_PAYMENT_CODE) {
			return 0;
		}

		if (!isModEnabled('prbonalim') || !$user->hasRight('bonalim@prbonalim', 'write')  || !$user->hasRight('bonalim@prbonalim', 'read')) {
			return 0;
		}

		$invoice = $object;
		$bankaccount = 0;
		$accountname = '';
		$error = 0;

		$now = dol_now();
		$res = 0;

		if ($invoice->total_ttc < 0) {
			return 0;
		}

		$db->begin();

		$constantforkey = 'CASHDESK_NO_DECREASE_STOCK'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
		$allowstockchange = (getDolGlobalString($constantforkey) != "1");

		if ($invoice->status != Facture::STATUS_DRAFT) {
			//If invoice is validated but it is not fully paid is not error and make the payment
			if ($invoice->getRemainToPay() > 0) {
				$res = 1;
			} else {
				dol_syslog("Sale already validated");
				dol_htmloutput_errors($langs->trans("InvoiceIsAlreadyValidated", "TakePos"), [], 1);
			}
		} elseif (count($invoice->lines) == 0) {
			$error++;
			dol_syslog('Sale without lines');
			dol_htmloutput_errors($langs->trans("NoLinesToBill", "TakePos"), [], 1);
		} elseif (isModEnabled('stock') && !isModEnabled('productbatch') && $allowstockchange) {
			// Validation of invoice with change into stock when produt/lot module is NOT enabled and stock change NOT disabled.
			// The case for isModEnabled('productbatch') is processed few lines later.
			$savconst = getDolGlobalString('STOCK_CALCULATE_ON_BILL');

			$conf->global->STOCK_CALCULATE_ON_BILL = 1;	// To force the change of stock during invoice validation

			$constantforkey = 'CASHDESK_ID_WAREHOUSE'.(isset($_SESSION["takeposterminal"]) ? $_SESSION["takeposterminal"] : '');
			dol_syslog("Validate invoice with stock change. Warehouse defined into constant ".$constantforkey." = ".getDolGlobalString($constantforkey));

			// Validate invoice with stock change into warehouse getDolGlobalInt($constantforkey)
			// Label of stock movement will be the same as when we validate invoice "Invoice XXXX validated"
			$batch_rule = 0;	// Module productbatch is disabled here, so no need for a batch_rule.
			$res = $invoice->validate($user, '', getDolGlobalInt($constantforkey), 0, $batch_rule);

			// Restore setup
			$conf->global->STOCK_CALCULATE_ON_BILL = $savconst;
		} else {
			// Validation of invoice with no change into stock (because param $idwarehouse is not fill)
			$res = $invoice->validate($user);
			if ($res < 0) {
				$error++;
				$langs->load("admin");
				dol_htmloutput_errors($invoice->error == 'NotConfigured' ? $langs->trans("NotConfigured").' (TakePos numbering module)' : $invoice->error, $invoice->errors, 1);
			}
		}

		// Add the payment
		if (!$error && $res >= 0) {
			$remaintopay = $invoice->getRemainToPay();

			if ($remaintopay > 0) {

				// If no amount is specified, take the remaining
				//  amount of invoice as amount of payment
				if ($amountofpayment <= 0 || $amountofpayment > $remaintopay) {
					$amountofpayment = $remaintopay;
				}

				// Consume BonAlim that are validated, older first
				// (do not check date_start and date_end for BonAlim validity)
				// TODO: check date_start and date_end for BonAlim validity
				$bonalim = new BonAlim($db);
				$bonalim_list = $bonalim->fetchAll(
					'ASC',
					'date_start',
					0,
					0,
					'(beneficiary:=:' . $invoice->socid . ') AND (status:=:' . BonAlim::STATUS_CREDITED . ')'
				);

				//$bankaccount = getDolGlobalInt('PRBONALIM_BANKACCOUNT_BONALIM');
				$bankaccount = 0;

				if ($bonalim_list) {
					foreach ($bonalim_list as $b) {

						if ($amountofpayment <= $b->amount_left) {
							// Consume BonAlim partially
							$res = $b->consume($amountofpayment, $user);
							if ($res < 0) {
								$error++;
								dol_htmloutput_errors($langs->trans('Error').' '.$$b->error, $$b->errors, 1);
							}

							// Save Payment
							$payment = new Paiement($db);
							$payment->datepaye = $now;
							$payment->fk_account = $bankaccount;
							$payment->amounts[$invoice->id] = $amountofpayment;
							$payment->paiementid = modPRBonAlim::BONALIM_PAYMENT_ID;
							$payment->num_payment = $invoice->ref;

							$res = $payment->create($user);
							if ($res < 0) {
								$error++;
								dol_htmloutput_errors($langs->trans('Error').' '.$payment->error, $payment->errors, 1);
							}

							$amountofpayment = 0;
							$remaintopay = 0;

							// Stop the Foreach loop
							break 1;
						} else {
							// Consume BonAlim totally
							$bonalim_amountleft = $b->amount_left;
							$b->consume($b->amount_left, $user);

							// Save Payment
							$payment = new Paiement($db);
							$payment->datepaye = $now;
							$payment->fk_account = $bankaccount;
							$payment->amounts[$invoice->id] = $bonalim_amountleft;
							$payment->paiementid = modPRBonAlim::BONALIM_PAYMENT_ID;
							$payment->num_payment = $invoice->ref;

							$res = $payment->create($user);
							if ($res < 0) {
								$error++;
								dol_htmloutput_errors($langs->trans('Error').' '.$payment->error, $payment->errors, 1);
							}

							// Remove from next payment the payment that has been done
							$amountofpayment = $amountofpayment - $bonalim_amountleft;
							$remaintopay = $remaintopay - $bonalim_amountleft;

							// Continue the Foreach loop
							// to get another BonAlim to pay the invoice
						}
					}
				} else {
					return 0;
				}
			}

			if ($remaintopay == 0) {
				dol_syslog("Invoice is paid, so we set it to status Paid");
				$result = $invoice->setPaid($user);
				if ($result > 0) {
					$invoice->paye = 1;
					$invoice->status = $invoice::STATUS_CLOSED;
				}
				// set payment method
				$invoice->setPaymentMethods(modPRBonAlim::BONALIM_PAYMENT_ID);
			} else {
				dol_syslog("Invoice is not paid, remain to pay = ".$remaintopay);
			}
		} else {
			dol_htmloutput_errors($invoice->error, $invoice->errors, 1);
		}

		// Update stock for batch products
		if (!$error && $res >= 0) {
			if (isModEnabled('stock') && isModEnabled('productbatch') && $allowstockchange) {
				// Update stocks
				dol_syslog("Now we record the stock movement for each qualified line");

				// The case !isModEnabled('productbatch') was processed few lines before.
				require_once DOL_DOCUMENT_ROOT . "/product/stock/class/mouvementstock.class.php";
				$constantforkey = 'CASHDESK_ID_WAREHOUSE'.$_SESSION["takeposterminal"];
				$inventorycode = dol_print_date(dol_now(), 'dayhourlog');
				// Label of stock movement will be "TakePOS - Invoice XXXX"
				$labeltakeposmovement = 'TakePOS - '.$langs->trans("Invoice").' '.$invoice->ref;

				foreach ($invoice->lines as $line) {
					// Use the warehouse id defined on invoice line else in the setup
					$warehouseid = ($line->fk_warehouse ? $line->fk_warehouse : getDolGlobalInt($constantforkey));

					// var_dump('fk_product='.$line->fk_product.' batch='.$line->batch.' warehouse='.$line->fk_warehouse.' qty='.$line->qty);
					if ($line->batch != '' && $warehouseid > 0) {
						$prod_batch = new Productbatch($db);
						$prod_batch->find(0, '', '', $line->batch, $warehouseid);

						$mouvP = new MouvementStock($db);
						$mouvP->setOrigin($invoice->element, $invoice->id);

						$res = $mouvP->livraison($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', $prod_batch->batch, $prod_batch->id, $inventorycode);
						if ($res < 0) {
							dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
							$error++;
						}
					} else {
						$mouvP = new MouvementStock($db);
						$mouvP->setOrigin($invoice->element, $invoice->id);

						$res = $mouvP->livraison($user, $line->fk_product, $warehouseid, $line->qty, $line->price, $labeltakeposmovement, '', '', '', '', 0, $inventorycode);
						if ($res < 0) {
							dol_htmloutput_errors($mouvP->error, $mouvP->errors, 1);
							$error++;
						}
					}
				}
			}
		}

		if (!$error && $res >= 0) {
			$db->commit();
		} else {
			$db->rollback();
		}

		return 1;
	}

	/**
	 * Execute action printFieldListFooter
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	public function printFieldListFooter(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user, $db;

		if (!(in_array('poslist', $hookmanager->contextarray) AND in_array('thirdpartylist', $hookmanager->contextarray))) {
			return 0;
		}

		// Enhance usability of search form

		ob_start();
		?>
		<script type="text/javascript">
			jQuery(document).ready(function() {
				var submitTimeout;
				var inputField = $('#searchFormList table.liste .liste_titre_filter input[name="search_nom"]');
				var lastText = inputField.val();

				$('#searchFormList > .liste_titre').hide();

				inputField.get(0).setSelectionRange(lastText.length, lastText.length);
				inputField.focus();

				inputField.on('keyup', function(e) {
					clearTimeout(submitTimeout);
					var callback = function(e) {
						$(e.target).parents('form').submit();
					};

					if (inputField.val() != lastText) {
						lastText = inputField.val();
						submitTimeout = setTimeout(callback.bind(this, e), 2000);
					}
				});
			});
		</script>
		<?php
		$resprints .= ob_get_clean();

		$this->resprints = $resprints;

		return 0;
	}


	/**
	 * Execute action addHtmlHeader
	 *
	 * @param	array<string,mixed>	$parameters		Array of parameters
	 * @param	CommonObject		$object			The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string				$action			'add', 'update', 'view'
	 * @param	Hookmanager			$hookmanager	Hookmanager
	 * @return	int									Return integer <0 if KO,
	 *												=0 if OK but we want to process standard actions too,
	 *												>0 if OK and we want to replace standard actions.
	 */
	/*
	public function addHtmlHeader(&$parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '<script>console.log("Hook addHtmlHeader exécuté");</script>';
		return 0;
	}
	*/
}
