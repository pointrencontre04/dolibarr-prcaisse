<?php
/* Copyright (C) 2025		SuperAdmin
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
 * \file    prcaisse/class/PRCaisseCronjobs.class.php
 * \ingroup prcaisse
 * \brief   File for PRCaisseCronjobs class
 */


/**
 *		Class to manage scheduled jobs of module PRCaisse
 */
class PRCaisseCronjobs
{
	/**
	 * @var DoliDB      Database handler
	 */
	public $db;

	/**
	 * @var string      Error message
	 * @see             $errors
	 */
	public $error;

	/**
	 * @var string[]    Array of error messages
	 */
	public $errors = array();

	/**
	 * @var string      Used by Cron method to return message
	 */
	public $output;

	/**
	 *	Constructor
	 *
	 *  @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Reset once a day, at the given time, the cash fund of each TakePOS terminal to its configured value.
	 *  The difference between the balance of the cash account of the terminal and the configured value
	 *  is transferred to (or from) the transfer account configured for the terminal.
	 *  CAN BE A CRON TASK (should be run every hour, the reset is done at the first run after $time)
	 *
	 *  @param	string      $time		Time of reset (HH:MM, server time)
	 *  @return	int						0 if OK, < 0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function prcaisse_reset_pos_cash($time = '01:00')
	{
		// phpcs:enable
		global $conf, $langs, $user;

		$langs->loadLangs(array("banks", "cashdesk", "prcaisse@prcaisse"));

		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

		dol_syslog("PRCaisseCronjobs::prcaisse_reset_pos_cash time=".$time, LOG_DEBUG);

		// Check time parameter
		$time = trim((string) $time);
		if (empty($time)) {
			$time = '01:00';
		}
		if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $reg) || (int) $reg[1] > 23 || (int) $reg[2] > 59) {
			$langs->load("errors");
			$this->error = $langs->transnoentitiesnoconv("ErrorBadValueForParameter", $time, "Time");
			$this->output = $this->error;
			return -1;
		}

		// Reset is done only once a day, at the first run after the given time
		$now = dol_now();
		$tmparray = dol_getdate($now, true);
		$datereset = dol_mktime((int) $reg[1], (int) $reg[2], 0, $tmparray['mon'], $tmparray['mday'], $tmparray['year']);
		$today = dol_print_date($now, '%Y-%m-%d', 'tzserver');

		if ($now < $datereset) {
			$this->output = $langs->trans("PRCaisseCashResetNotYet", $time);
			return 0;
		}
		if (getDolGlobalString('PRCAISSE_CASH_RESET_LASTDATE') == $today) {
			$this->output = $langs->trans("PRCaisseCashResetAlreadyDone", $today);
			return 0;
		}

		$count = 0;
		$counterror = 0;
		$output = array();

		$nbterminals = max(1, getDolGlobalInt('TAKEPOS_NUM_TERMINALS'));
		for ($terminal = 1; $terminal <= $nbterminals; $terminal++) {
			$terminalname = getDolGlobalString('TAKEPOS_TERMINAL_NAME_'.$terminal, $langs->transnoentitiesnoconv("TerminalName", $terminal));

			// Terminal with reset disabled is not managed
			if (!getDolGlobalInt('PRCAISSE_CASH_RESET_ENABLED'.$terminal)) {
				continue;
			}
			$cashlevel = (float) price2num(getDolGlobalString('PRCAISSE_CASH_RESET_AMOUNT'.$terminal, '0'), 'MT');

			$cashaccountid = getDolGlobalInt('CASHDESK_ID_BANKACCOUNT_CASH'.$terminal);
			$transferaccountid = getDolGlobalInt('PRCAISSE_CASH_DEPOSIT_ACCOUNT'.$terminal);
			if ($cashaccountid <= 0 || $transferaccountid <= 0 || $cashaccountid == $transferaccountid) {
				$counterror++;
				$output[] = $langs->trans("PRCaisseCashResetSetupNotComplete", $terminalname);
				continue;
			}

			$cashaccount = new Account($this->db);
			$transferaccount = new Account($this->db);
			if ($cashaccount->fetch($cashaccountid) <= 0 || $transferaccount->fetch($transferaccountid) <= 0) {
				$counterror++;
				$output[] = $terminalname.': '.$cashaccount->error.' '.$transferaccount->error;
				continue;
			}
			if ($cashaccount->currency_code != $transferaccount->currency_code) {
				$counterror++;
				$output[] = $langs->trans("PRCaisseCashResetCurrencyDiffers", $terminalname);
				continue;
			}

			$balance = $cashaccount->solde(1);
			if (!empty($cashaccount->errors)) {
				$counterror++;
				$output[] = $terminalname.': '.implode(', ', $cashaccount->errors);
				continue;
			}

			$delta = (float) price2num($balance - $cashlevel, 'MT');
			if ($delta == 0) {
				continue;
			}

			// Positive delta: excess of cash is transferred out of the cash account, negative delta: cash fund is completed
			if ($delta > 0) {
				$accountfrom = $cashaccount;
				$accountto = $transferaccount;
			} else {
				$accountfrom = $transferaccount;
				$accountto = $cashaccount;
			}
			$amount = abs($delta);

			// By default, electronic transfer from bank to bank
			$type = 'VIR';
			if ($accountto->type == Account::TYPE_CASH || $accountfrom->type == Account::TYPE_CASH) {
				// This is transfer of change
				$type = 'LIQ';
			}

			$label = $langs->transnoentitiesnoconv("PRCaisseCashResetLabel", $terminalname);

			$error = 0;
			$this->db->begin();

			$bank_line_id_from = $accountfrom->addline($now, $type, $label, price2num(-1 * $amount), '', 0, $user);
			if (!($bank_line_id_from > 0)) {
				$error++;
			}
			if (!$error) {
				$bank_line_id_to = $accountto->addline($now, $type, $label, price2num($amount), '', 0, $user);
				if (!($bank_line_id_to > 0)) {
					$error++;
				}
			}
			if (!$error) {
				$result = $accountfrom->add_url_line($bank_line_id_from, $bank_line_id_to, DOL_URL_ROOT.'/compta/bank/line.php?rowid=', '(banktransfert)', 'banktransfert');
				if (!($result > 0)) {
					$error++;
				}
			}
			if (!$error) {
				$result = $accountto->add_url_line($bank_line_id_to, $bank_line_id_from, DOL_URL_ROOT.'/compta/bank/line.php?rowid=', '(banktransfert)', 'banktransfert');
				if (!($result > 0)) {
					$error++;
				}
			}

			if (!$error) {
				$this->db->commit();
				$count++;
				$output[] = $terminalname.': '.$langs->trans("TransferFromToDone", $accountfrom->label, $accountto->label, price($amount), $langs->transnoentitiesnoconv("Currency".$conf->currency));
			} else {
				$this->db->rollback();
				$counterror++;
				$output[] = $terminalname.': '.$accountfrom->error.' '.$accountto->error;
			}
		}

		if ($counterror > 0) {
			// Last date is not saved so the reset is retried at next run (terminals already reset have no more delta)
			$this->error = $langs->trans("PRCaisseCashResetNErrors", $counterror);
			$this->errors = $output;
			$this->output = $this->error.'<br>'.implode('<br>', $output);
			return -1;
		}

		dolibarr_set_const($this->db, 'PRCAISSE_CASH_RESET_LASTDATE', $today, 'chaine', 0, '', $conf->entity);

		if ($count > 0) {
			$this->output = $langs->trans("PRCaisseCashResetNDone", $count).'<br>'.implode('<br>', $output);
		} else {
			$this->output = $langs->trans("PRCaisseCashResetNothingToDo");
		}

		return 0; // This function can be called by cron so must return 0 if OK
	}
}
