<?php
/**
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Viktar Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\SURF_Trashbin\AppInfo;

use OCP\AppFramework\App;
use OCA\SURF_Trashbin\Trashbin;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('surf_trashbin', $urlParams);

		$container = $this->getContainer();

		/*
		 * Register trashbin service
		 */
		$container->registerService('Trashbin', function ($c) {
			return new Trashbin(
				$c->getServer()->getLazyRootFolder(),
				$c->getServer()->getUrlGenerator(),
				$c->getServer()->getEventDispatcher()
			);
		});
	}
}
