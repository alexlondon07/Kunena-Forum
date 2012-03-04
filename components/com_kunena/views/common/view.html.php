<?php
/**
 * Kunena Component
 * @package Kunena.Site
 * @subpackage Views
 *
 * @copyright (C) 2008 - 2011 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

jimport ( 'joomla.cache.handler.output' );
jimport ( 'joomla.document.html.html' );

/**
 * Common view
 */
class KunenaViewCommon extends KunenaView {
	public $catid = 0;
	protected $offline = false;

	function display($layout = null, $tpl = null) {
		$this->assignRef ( 'state', $this->get ( 'State' ) );

		if ($this->config->board_offline && ! $this->me->isAdmin ()) {
			$this->offline = true;
		}
		return $this->displayLayout($layout, $tpl);
	}

	function displayDefault($tpl = null) {
		$result = $this->loadTemplateFile($tpl);
		if (JError::isError($result)) {
			return $result;
		}
		echo $result;
	}

	function displayAnnouncement($tpl = null) {
		if ($this->offline) return;

		if ($this->config->showannouncement > 0) {
			$new = new KunenaForumAnnouncement;
			$this->announcement = $this->get('Announcement');
			if (!$this->announcement) {
				echo ' ';
				return;
			}

			$canCreate = (int) $new->authorise('create');
			$canEdit = (int) $this->announcement->authorise('edit');
			$canDelete = (int) $this->announcement->authorise('delete');

			$cache = JFactory::getCache('com_kunena', 'output');
			if ($cache->start("{$this->ktemplate->name}.common.announcement.{$canCreate}{$canEdit}{$canDelete}", 'com_kunena.template')) return;

			if ($this->announcement && $this->announcement->authorise('read')) {
				$this->actions = array();
				$this->annUrl = $this->announcement->getLayoutUrl('default', 'object');
				$this->annListUrl = KunenaForumAnnouncementHelper::getLayoutUrl('list', 'object');
				if ($canEdit) $this->actions['edit'] = $this->announcement->getLayoutUrl('edit', 'object');
				if ($canDelete) $this->actions['delete'] = $this->announcement->getTaskUrl('delete', 'object');
				if ($canCreate) $this->actions['add'] = $new->getLayoutUrl('create', 'object');
				if ($this->actions) $this->actions['cpanel'] = KunenaForumAnnouncementHelper::getLayoutUrl('list', 'object');

				$this->annTitle = KunenaHtmlParser::parseText($this->announcement->title);
				$this->annDescription = $this->announcement->sdescription ? KunenaHtmlParser::parseBBCode($this->announcement->sdescription) : KunenaHtmlParser::parseBBCode($this->announcement->description, 300);
				$this->annDate = $this->announcement->getCreationDate();
				$this->showdate = $this->announcement->showdate;
				$result = $this->loadTemplateFile($tpl);
				if (JError::isError($result)) {
					return $result;
				}
				echo $result;
			} else {
				echo ' ';
			}
			$cache->end();
		} else echo ' ';
	}

	function displayForumJump($tpl = null) {
		if ($this->offline) return;

		$options = array ();
		$options [] = JHTML::_ ( 'select.option', '0', JText::_('COM_KUNENA_FORUM_TOP') );
		$cat_params = array ('sections'=>1, 'catid'=>0);
		$this->assignRef ( 'categorylist', JHTML::_('kunenaforum.categorylist', 'catid', 0, $options, $cat_params, 'class="inputbox fbs" size="1" onchange = "this.form.submit()"', 'value', 'text', $this->catid));

		$result = $this->loadTemplateFile($tpl);
		if (JError::isError($result)) {
			return $result;
		}
		echo $result;
	}

	function displayBreadcrumb($tpl = null) {
		if ($this->offline) return;

		$catid = JRequest::getInt ( 'catid', 0 );
		$id = JRequest::getInt ( 'id', 0 );
		$view = JRequest::getWord ( 'view', 'default' );
		$layout = JRequest::getWord ( 'layout', 'default' );

		$pathway = $this->app->getPathway();
		$active = $this->app->getMenu ()->getActive ();

		if (empty($this->pathway)) {
			KunenaFactory::loadLanguage('com_kunena.sys', 'admin');
			if ($catid) {
				$parents = KunenaForumCategoryHelper::getParents($catid);
				$parents[$catid] = KunenaForumCategoryHelper::get($catid);

				// Remove categories from pathway if menu item contains/excludes them
				if (!empty($active->query['catid']) && isset($parents[$active->query['catid']])) {
					$curcatid = $active->query['catid'];
					while (($item = array_shift($parents)) !== null) {
						if ($item->id == $curcatid) break;
					}
				}
				foreach ( $parents as $parent ) {
					$pathway->addItem($this->escape( $parent->name ), KunenaRoute::normalize("index.php?option=com_kunena&view=category&catid={$parent->id}"));
				}
			}
			if ($id) {
				$topic = KunenaForumTopicHelper::get($id);
				$pathway->addItem($this->escape( $topic->subject ), KunenaRoute::normalize("index.php?option=com_kunena&view=category&catid={$catid}&id={$topic->id}"));
			}
			if ($view == 'topic') {
				$active_layout = (!empty($active->query['view']) && $active->query['view'] == 'topic' && !empty($active->query['layout'])) ? $active->query['layout'] : '';
				switch ($layout) {
					case 'create':
						if ($active_layout != 'create') $pathway->addItem($this->escape( JText::_('COM_KUNENA_MENU_TOPIC_CREATE'), KunenaRoute::normalize() ));
						break;
					case 'reply':
						if ($active_layout != 'reply') $pathway->addItem($this->escape( JText::_('COM_KUNENA_MENU_TOPIC_REPLY'), KunenaRoute::normalize() ));
						break;
					case 'edit':
						if ($active_layout != 'edit') $pathway->addItem($this->escape( JText::_('COM_KUNENA_MENU_TOPIC_EDIT'), KunenaRoute::normalize() ));
						break;
				}
			}
		}
		$this->pathway = array();
		foreach ($pathway->getPathway() as $pitem) {
			$item = new StdClass();
			$item->name = $this->escape($pitem->name);
			$item->link = KunenaRoute::_($pitem->link);
			if ($item->link) $this->pathway[] = $item;
		}

		$result = $this->loadTemplateFile($tpl);
		if (JError::isError($result)) {
			return $result;
		}
		echo $result;
	}

	function displayWhosonline($tpl = null) {
		if ($this->offline) return;

		$moderator = intval($this->me->isModerator());
		$cache = JFactory::getCache('com_kunena', 'output');
		if ($cache->start("{$this->ktemplate->name}.common.whosonline.{$moderator}", "com_kunena.template")) return;

		$users = KunenaUserHelper::getOnlineUsers();
		KunenaUserHelper::loadUsers(array_keys($users));
		$onlineusers = KunenaUserHelper::getOnlineCount();

		$who = '<strong>'.$onlineusers['user'].' </strong>';
		if($onlineusers['user']==1) {
			$who .= JText::_('COM_KUNENA_WHO_ONLINE_MEMBER').'&nbsp;';
		} else {
			$who .= JText::_('COM_KUNENA_WHO_ONLINE_MEMBERS').'&nbsp;';
		}
		$who .= JText::_('COM_KUNENA_WHO_AND');
		$who .= '<strong> '. $onlineusers['guest'].' </strong>';
		if($onlineusers['guest']==1) {
			$who .= JText::_('COM_KUNENA_WHO_ONLINE_GUEST').'&nbsp;';
		} else {
			$who .= JText::_('COM_KUNENA_WHO_ONLINE_GUESTS').'&nbsp;';
		}
		$who .= JText::_('COM_KUNENA_WHO_ONLINE_NOW');
		$this->membersOnline = $who;

		$this->onlineList = array();
		$this->hiddenList = array();
		foreach ($users as $userid=>$usertime) {
			$user = KunenaUserHelper::get($userid);
			if ( !$user->showOnline ) {
				if ($this->me->isModerator()) $this->hiddenList[$user->getName()] = $user;
			} else {
				$this->onlineList[$user->getName()] = $user;
			}
		}
		ksort($this->onlineList);
		ksort($this->hiddenList);

		$this->usersURL = CKunenaLink::GetUserlistURL('');

		$result = $this->loadTemplateFile($tpl);
		if (JError::isError($result)) {
			return $result;
		}
		echo $result;
		$cache->end();
	}

	function displayStatistics($tpl = null) {
		if ($this->offline) return;

		$cache = JFactory::getCache('com_kunena', 'output');
		if ($cache->start("{$this->ktemplate->name}.common.statistics", 'com_kunena.template')) return;

		// FIXME: refactor code
		require_once(KPATH_SITE.'/lib/kunena.link.class.php');
		$kunena_stats = KunenaForumStatistics::getInstance ( );
		$kunena_stats->loadGeneral();

		$this->assign($kunena_stats);
		$this->latestMemberLink = KunenaFactory::getUser(intval($this->lastUserId))->getLink();
		$this->statisticsURL = KunenaRoute::_('index.php?option=com_kunena&view=statistics');

		$result = $this->loadTemplateFile($tpl);
		if (JError::isError($result)) {
			return $result;
		}
		echo $result;
		$cache->end();
	}

	function displayMenu($tpl = null) {
		if ($this->offline) return;

		$this->params = $this->state->get('params');
		$this->getPrivateMessageLink();
		$result = $this->loadTemplateFile($tpl);
		if (JError::isError($result)) {
			return $result;
		}
		echo $result;
	}

	function displayLoginBox($tpl = null) {
		if ($this->offline) return;

		$my = JFactory::getUser ();
		$cache = JFactory::getCache('com_kunena', 'output');
		$cachekey = "{$this->ktemplate->name}.common.loginbox.u{$my->id}";
		$cachegroup = 'com_kunena.template';

		$contents = $cache->get($cachekey, $cachegroup);
		if (!$contents) {
			require_once KPATH_SITE . '/lib/kunena.link.class.php';

			$this->assign ( 'moduleHtml', $this->getModulePosition('kunena_profilebox'));

			$login = KunenaLogin::getInstance();
			if ($my->get ( 'guest' )) {
				$this->setLayout('login');
				if ($login) {
					$this->assignRef ( 'login', $login );
					$this->assignRef ( 'register', $login->getRegistrationURL() );
					$this->assignRef ( 'lostpassword', $login->getResetURL() );
					$this->assignRef ( 'lostusername', $login->getRemindURL() );
				}
			} else {
				$this->setLayout('logout');
				if ($login) $this->assignRef ( 'logout', $login );
				$this->lastvisitDate = KunenaDate::getInstance($this->me->lastvisitDate);

				// Private messages
				$this->getPrivateMessageLink();

				// TODO: Edit profile (need to get link to edit page, even with integration)
				//$this->assign ( 'editProfileLink', '<a href="' . $url.'">'. JText::_('COM_KUNENA_PROFILE_EDIT').'</a>');

				// Announcements
				if ( $this->me->isModerator()) {
					$this->assign ( 'announcementsLink', '<a href="' . KunenaForumAnnouncementHelper::getLayoutUrl('list').'">'. JText::_('COM_KUNENA_ANN_ANNOUNCEMENTS').'</a>');
				}

			}
			$contents = $this->loadTemplateFile($tpl);
			if (JError::isError($contents)) {
				return $contents;
			}
			$cache->store($contents, $cachekey, $cachegroup);
		}
		$contents = preg_replace_callback('|\[K=(\w+)(?:\:([\w-_]+))?\]|', array($this, 'fillLoginBoxInfo'), $contents);
		echo $contents;
	}

	function fillLoginBoxInfo($matches) {
		switch ($matches[1]) {
			case 'RETURN_URL':
				return base64_encode ( JFactory::getURI ()->toString ( array ('path', 'query', 'fragment' ) ) );
			case 'TOKEN':
				return JHTML::_ ( 'form.token' );
			case 'MODULE':
				return $this->getModulePosition('kunena_profilebox');
		}
	}

	function displayFooter($tpl = null) {
		if ($this->offline) return;

		require_once KPATH_SITE . '/lib/kunena.link.class.php';
		$catid = 0;
		if ($this->config->enablerss) {
			if ($catid > 0) {
				$category = KunenaForumCategoryHelper::get ( $catid );
				if ($category->pub_access == 0 && $category->parent)
					$rss_params = '&catid=' . ( int ) $catid;
			} else {
				$rss_params = '';
			}
			if (isset ( $rss_params )) {
				$document = JFactory::getDocument ();
				$document->addCustomTag ( '<link rel="alternate" type="application/rss+xml" title="' . JText::_ ( 'COM_KUNENA_LISTCAT_RSS' ) . '" href="' . CKunenaLink::GetRSSURL ( $rss_params ) . '" />' );
				$this->assign ( 'rss', CKunenaLink::GetRSSLink ( $this->getIcon ( 'krss', JText::_('COM_KUNENA_LISTCAT_RSS') ), 'follow', $rss_params ));
			}
		}
		$template = KunenaFactory::getTemplate ();
		$credits = $this->getTeamCreditsLink ( JText::_('COM_KUNENA_POWEREDBY') ) . ' ' . $this->getCreditsLink ();
		if ($template->params->get('templatebyText') !='') {
			$credits .= ' :: <a href ="'. $template->params->get('templatebyLink').'" rel="follow">' . $template->params->get('templatebyText') .' '. $template->params->get('templatebyName') .'</a>';
		}
		$this->assign ( 'credits', $credits );
		$result = $this->loadTemplateFile($tpl);
		if (JError::isError($result)) {
			return $result;
		}
		echo $result;
	}

	function getCreditsLink() {
		return CKunenaLink::GetHrefLink ( 'http://www.kunena.org', 'Kunena', 'Kunena', 'follow', NULL, NULL, 'target="_blank"' );
	}

	function getTeamCreditsLink($name = '') {
		return JHTML::_('kunenaforum.link', "index.php?option=com_kunena&view=credits", $name, '', '', 'follow');
	}

	function getPrivateMessageLink() {
		// Private messages
		$private = KunenaFactory::getPrivateMessaging();
		if ($private) {
			$count = $private->getUnreadCount($this->me->userid);
			$this->assign ( 'privateMessagesLink', $private->getInboxLink($count ? JText::sprintf('COM_KUNENA_PMS_INBOX_NEW', $count) : JText::_('COM_KUNENA_PMS_INBOX')));
		}
	}
}