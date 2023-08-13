/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
// const urlParams = new URLSearchParams(window.location.search);
// const groupName = 'biochemistry';//urlParams.get('view').substring(13);
// const contentId = 'app-content-surftrashbin-' + groupName;
$(document).ready(function() {
	const contents = $('div[id^="app-content-surftrashbin-"]');

	for (var i = 0; i < contents.length; i++) {
		let contentId = contents[i].id;
		let groupName = contentId.substring(25);

		if (OCA[groupName] == null) {
			OCA[groupName] = {};
			OCA[groupName].App = {
				_initialized: false,

				initialize: function($el) {
					if (this._initialized) {
						return;
					}
					this._initialized = true;
					asdasdasd(groupName);
					var urlParams = OC.Util.History.parseUrlQuery();
					this.fileList = new OCA[groupName].FileList(
						$('#' + contentId), {
							scrollContainer: $('#app-content'),
							fileActions: this._createFileActions(),
							detailsViewEnabled: false,
							scrollTo: urlParams.scrollto,
							config: OCA.Files.App.getFilesConfig()
						}
					);
				},

				_createFileActions: function() {
					var fileActions = new OCA.Files.FileActions();
					fileActions.register('dir', 'Open', OC.PERMISSION_READ, '', function (filename, context) {
						var dir = context.fileList.getCurrentDirectory();
						context.fileList.changeDirectory(OC.joinPaths(dir, filename));
					});

					fileActions.setDefault('dir', 'Open');

					fileActions.registerAction({
						name: 'Restore',
						displayName: t('files_trashbin', 'Restore'),
						type: OCA.Files.FileActions.TYPE_INLINE,
						mime: 'all',
						permissions: OC.PERMISSION_READ,
						iconClass: 'icon-history',
						actionHandler: function(filename, context) {
							var fileList = context.fileList;
							var tr = fileList.findFileEl(filename);
							var deleteAction = tr.children("td.date").children(".action.delete");
							deleteAction.removeClass('icon-delete').addClass('icon-loading-small');
							fileList.disableActions();
							$.post(OC.filePath('surf_trashbin', 'ajax', 'undelete.php'), {
									files: JSON.stringify([filename]),
									dir: fileList.getCurrentDirectory(),
									group: groupName
								},
								_.bind(fileList._removeCallback, fileList)
							);
						}
					});

					fileActions.registerAction({
						name: 'Delete',
						displayName: t('files_trashbin', 'Delete'),
						mime: 'all',
						permissions: OC.PERMISSION_READ,
						iconClass: 'icon-delete',
						render: function(actionSpec, isDefault, context) {
							var $actionLink = fileActions._makeActionLink(actionSpec, context);
							$actionLink.attr('original-title', t('files_trashbin', 'Delete permanently'));
							$actionLink.children('img').attr('alt', t('files_trashbin', 'Delete permanently'));
							context.$file.find('td:last').append($actionLink);
							return $actionLink;
						},
						actionHandler: function(filename, context) {
							var fileList = context.fileList;
							$('.tipsy').remove();
							var tr = fileList.findFileEl(filename);
							var deleteAction = tr.children("td.date").children(".action.delete");
							deleteAction.removeClass('icon-delete').addClass('icon-loading-small');
							fileList.disableActions();
							$.post(OC.filePath('surf_trashbin', 'ajax', 'delete.php'), {
									files: JSON.stringify([filename]),
									dir: fileList.getCurrentDirectory(),
									group: groupName
								},
								_.bind(fileList._removeCallback, fileList)
							);
						}
					});
					return fileActions;
				}
			};

			$('#' + contentId).one('show', function() {
				var App = OCA[groupName].App;
				App.initialize($('#' + contentId));
				// force breadcrumb init
				// App.fileList.changeDirectory(App.fileList.getCurrentDirectory(), false, true);
			});
		}

	}
});

