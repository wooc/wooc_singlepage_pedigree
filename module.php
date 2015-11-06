<?php
// Classes and libraries for module system
//
// webtrees: Web based Family History software
// Copyright (C) 2014 webtrees development team.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
//

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Module;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleReportInterface;

/**
 * Class WoocSinglepagePedigreeModule
 */
class WoocSinglepagePedigreeModule extends AbstractModule implements ModuleReportInterface {
	/** {@inheritDoc} */
	
	public function getTitle() {
		return I18N::translate('Wooc Single Page Pedigree Report');
	}

	public function getReportTitle() {
		// This text also appears in the .XML file - update both together
		return /* I18N: Name of a report */ I18N::translate('Pedigree - single page');
	}

	/** {@inheritDoc} */
	public function getDescription() {
		// This text also appears in the .XML file - update both together
		return /* I18N: Description of the “Pedigree” module */ I18N::translate('Print a pedigree chart on a single page.');
	}

	/** {@inheritDoc} */
	public function defaultAccessLevel() {
		return Auth::PRIV_PRIVATE;
	}

	/** {@inheritDoc} */
	public function getReportMenu() {
		global $controller, $WT_TREE;

		require_once WT_ROOT . WT_MODULES_DIR . $this->getName() . '/class_pedigree.php';

		return new Menu(
			$this->getReportTitle(),
			'reportengine.php?ged=' . $WT_TREE->getNameUrl() . '&amp;action=setup&amp;report=' . WT_MODULES_DIR . $this->getName() . '/report_singlepage.xml&amp;pid=' . $controller->getSignificantIndividual()->getXref(),
			'menu-report-single' . $this->getName(),
			array('rel' => 'nofollow')
		);
	}
}

return new WoocSinglepagePedigreeModule(__DIR__);
