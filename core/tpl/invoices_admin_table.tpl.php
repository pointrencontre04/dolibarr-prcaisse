<?php
/* Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

/**
 * @var Conf $conf
 * @var Array $arrayoflines
 */


// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

?>

<table class="tagtable nobottomiftotal noborder liste">
	<tr class="liste_titre">
		<td>Réf</td>
		<td>Date</td>
		<td>Caisse</td>
		<td>Montant</td>
		<td>À régler</td>
	</tr>
	<?php foreach ($arrayoflines as $line): ?>
		<tr class="invoice_history_line table_row <?php if ($invoice_id && $invoice_id == $line['facid']) { echo 'active'; } ?>">
			<td class="ref"><?php echo '<a href="/compta/facture/card.php?id=' . $line['facid'] . '"><span class="fas fa-file-invoice-dollar infobox-commande paddingright " style=""></span>' . $line['ref'] . '</a>'; ?></td>
			<td class="date"><?php echo dol_print_date($line['date'], 'day') ?></td>
			<td class="pos_source"><?php echo getDolGlobalString('TAKEPOS_TERMINAL_NAME_' . $line['pos_source']); ?></td>
			<td class="amount">
				<div class="cell_inner">
					<?php echo ($line['type'] == Facture::TYPE_DEPOSIT ? '<span class="invoice_type invoice_type_deposit">A</span>' : ''); ?>
					<?php echo ($line['type'] == Facture::TYPE_STANDARD ? '<span class="invoice_type invoice_type_standard">D</span>' : ''); ?>
					<?php echo '<span>' . price($line['amount_gross'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) . '</span>'; ?>
				</div>
			</td>
			<?php if ($line['status'] == Facture::STATUS_CLOSED && $line['close_code'] == 0 && $line['encours'] == 0): ?>
				<td class="encours paid">Payé</td>
			<?php elseif ($line['status'] == Facture::STATUS_ABANDONED): ?>
				<td class="encours closed">Fermé</td>
			<?php else: ?>
				<td class="encours unpaid"><?php echo price($line['encours'], 0, $langs, 0, 0, -1, $conf->currency, 0, $langs, 0, 0, -1, $conf->currency) ?></td>
			<?php endif; ?>
		</tr>
	<?php endforeach; ?>
</table>