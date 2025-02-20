<?php

declare(strict_types=1);

/*
    +-----------------------------------------------------------------------------+
    | ILIAS open source                                                           |
    +-----------------------------------------------------------------------------+
    | Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
    |                                                                             |
    | This program is free software; you can redistribute it and/or               |
    | modify it under the terms of the GNU General Public License                 |
    | as published by the Free Software Foundation; either version 2              |
    | of the License, or (at your option) any later version.                      |
    |                                                                             |
    | This program is distributed in the hope that it will be useful,             |
    | but WITHOUT ANY WARRANTY; without even the implied warranty of              |
    | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
    | GNU General Public License for more details.                                |
    |                                                                             |
    | You should have received a copy of the GNU General Public License           |
    | along with this program; if not, write to the Free Software                 |
    | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
    +-----------------------------------------------------------------------------+
*/


/**
* XML writer class
*
* Class for writing xml export versions of courses
*
* @author Stefan Meyer <meyer@leifos.com>
* @version $Id: class.ilGroupXMLWriter.php 16108 2008-02-28 17:36:41Z rkuester $
*/
class ilGroupXMLWriter extends ilXmlWriter
{
    public const MODE_SOAP = 1;
    public const MODE_EXPORT = 2;
    public const EXPORT_VERSION = 3;

    private int $mode = self::MODE_SOAP;

    private ilLogger $logger;
    private ilSetting $settings;
    private ilAccessHandler $access;

    private ilObjGroup $group_obj;
    private ilGroupParticipants $participants;
    private bool $attach_users = true;

    public function __construct(ilObjGroup $group_obj)
    {
        global $DIC;

        $this->logger = $DIC->logger()->grp();
        $this->settings = $DIC->settings();
        $this->access = $DIC->access();
        parent::__construct();
        $this->group_obj = $group_obj;
        $this->participants = ilGroupParticipants::_getInstanceByObjId($this->group_obj->getId());
    }

    public function setMode(int $a_mode): void
    {
        $this->mode = $a_mode;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function start(): void
    {
        if ($this->getMode() == self::MODE_SOAP) {
            $this->logger->debug('Using soap mode');
            $this->__buildHeader();
            $this->__buildGroup();
            $this->__buildMetaData();
            $this->__buildAdvancedMetaData();
            $this->__buildTitleDescription();
            $this->__buildRegistration();
            $this->__buildExtraSettings();
            if ($this->attach_users) {
                $this->__buildAdmin();
                $this->__buildMember();
            }
            ilContainerSortingSettings::_exportContainerSortingSettings($this, $this->group_obj->getId());
            ilContainer::_exportContainerSettings($this, $this->group_obj->getId());
            $this->__buildFooter();
        } elseif ($this->getMode() == self::MODE_EXPORT) {
            $this->logger->debug('Using export mode');
            $this->__buildGroup();
            $this->__buildMetaData();
            $this->__buildAdvancedMetaData();
            $this->__buildTitleDescription();
            $this->__buildRegistration();
            $this->__buildExtraSettings();
            $this->__buildPeriod();
            ilContainerSortingSettings::_exportContainerSortingSettings($this, $this->group_obj->getId());
            ilContainer::_exportContainerSettings($this, $this->group_obj->getId());
            $this->__buildFooter();
        }
    }

    public function getXML(): string
    {
        return $this->xmlDumpMem(false);
    }

    // PRIVATE
    public function __buildHeader(): bool
    {
        $this->xmlSetDtdDef("<!DOCTYPE group PUBLIC \"-//ILIAS//DTD Group//EN\" \"" . ILIAS_HTTP_PATH . "/xml/ilias_group_3_10.dtd\">");
        $this->xmlSetGenCmt("Export of ILIAS group " . $this->group_obj->getId() . " of installation " . $this->settings->get('inst_id') . ".");
        $this->xmlHeader();
        return true;
    }

    public function __buildGroup(): void
    {
        $attrs["exportVersion"] = self::EXPORT_VERSION;
        $attrs["id"] = "il_" . $this->settings->get('inst_id') . '_grp_' . $this->group_obj->getId();

        switch ($this->group_obj->readGroupStatus()) {
            case ilGroupConstants::GRP_TYPE_OPEN:
                $attrs['type'] = 'open';
                break;

            case ilGroupConstants::GRP_TYPE_CLOSED:
            default:
                $attrs['type'] = 'closed';
                break;
        }
        $this->xmlStartTag("group", $attrs);
    }

    protected function __buildMetaData(): bool
    {
        $md2xml = new ilMD2XML($this->group_obj->getId(), $this->group_obj->getId(), 'grp');
        $md2xml->startExport();
        $this->appendXML($md2xml->getXML());
        return true;
    }

    private function __buildAdvancedMetaData(): void
    {
        ilAdvancedMDValues::_appendXMLByObjId($this, $this->group_obj->getId());
    }


    public function __buildTitleDescription(): void
    {
        $this->xmlElement('title', null, $this->group_obj->getTitle());

        if ($desc = $this->group_obj->getDescription()) {
            $this->xmlElement('description', null, $desc);
        }

        $attr['id'] = 'il_' . $this->settings->get('inst_id') . '_usr_' . $this->group_obj->getOwner();
        $this->xmlElement('owner', $attr);

        $this->xmlElement('information', null, $this->group_obj->getInformation());
    }

    /**
     * Add group period settings to xml
     */
    protected function __buildPeriod(): void
    {
        if (!$this->group_obj->getStart() || !$this->group_obj->getEnd()) {
            return;
        }

        $this->xmlStartTag(
            'period',
            [
                'withTime' => $this->group_obj->getStartTimeIndication()
            ]
        );
        $this->xmlElement(
            'start',
            null,
            $this->group_obj->getStart() ?
                $this->group_obj->getStart()->get(IL_CAL_UNIX) :
                null
        );
        $this->xmlElement(
            'end',
            null,
            $this->group_obj->getEnd()->get(IL_CAL_UNIX) ?
                $this->group_obj->getEnd()->get(IL_CAL_UNIX) :
                null
        );

        $this->xmlEndTag('period');
    }

    public function __buildRegistration(): void
    {

        // registration type
        switch ($this->group_obj->getRegistrationType()) {
            case ilGroupConstants::GRP_REGISTRATION_DIRECT:
                $attrs['type'] = 'direct';
                break;
            case ilGroupConstants::GRP_REGISTRATION_REQUEST:
                $attrs['type'] = 'confirmation';
                break;
            case ilGroupConstants::GRP_REGISTRATION_PASSWORD:
                $attrs['type'] = 'password';
                break;

            default:
            case ilGroupConstants::GRP_REGISTRATION_DEACTIVATED:
                $attrs['type'] = 'disabled';
                break;
        }
        $attrs['waitingList'] = $this->group_obj->isWaitingListEnabled() ? 'Yes' : 'No';

        $this->xmlStartTag('registration', $attrs);

        if (strlen($pwd = $this->group_obj->getPassword())) {
            $this->xmlElement('password', null, $pwd);
        }


        // limited registration period
        if (!$this->group_obj->isRegistrationUnlimited()) {
            $this->xmlStartTag('temporarilyAvailable');
            $this->xmlElement('start', null, $this->group_obj->getRegistrationStart()->get(IL_CAL_UNIX));
            $this->xmlElement('end', null, $this->group_obj->getRegistrationEnd()->get(IL_CAL_UNIX));
            $this->xmlEndTag('temporarilyAvailable');
        }

        // max members
        $attrs = array();
        $attrs['enabled'] = $this->group_obj->isMembershipLimited() ? 'Yes' : 'No';
        $this->xmlElement('maxMembers', $attrs, $this->group_obj->getMaxMembers());
        $this->xmlElement('minMembers', null, $this->group_obj->getMinMembers());
        $this->xmlElement('WaitingListAutoFill', null, (int) $this->group_obj->hasWaitingListAutoFill());
        $this->xmlElement('CancellationEnd', null, ($this->group_obj->getCancellationEnd() && !$this->group_obj->getCancellationEnd()->isNull()) ? $this->group_obj->getCancellationEnd()->get(IL_CAL_UNIX) : null);

        $this->xmlElement('mailMembersType', null, (string) $this->group_obj->getMailToMembersType());

        $this->xmlEndTag('registration');
    }

    /**
     * Build extra settings, like "show member list"
     */
    public function __buildExtraSettings(): void
    {
        $this->xmlElement('showMembers', null, $this->group_obj->getShowMembers());
        $this->xmlElement('admissionNotification', null, $this->group_obj->getAutoNotification() ? 1 : 0);

        $this->xmlElement('ViewMode', null, ilObjGroup::lookupViewMode($this->group_obj->getId()));
        $this->xmlElement(
            'SessionLimit',
            [
                'active' => $this->group_obj->isSessionLimitEnabled() ? 1 : 0,
                'previous' => $this->group_obj->getNumberOfPreviousSessions(),
                'next' => $this->group_obj->getNumberOfNextSessions()
            ]
        );
    }

    public function __buildAdmin(): void
    {
        $admins = $this->group_obj->getGroupAdminIds();
        $admins = $this->access->filterUserIdsByRbacOrPositionOfCurrentUser(
            'manage_members',
            ilOrgUnitOperation::OP_MANAGE_MEMBERS,
            $this->group_obj->getRefId(),
            $admins
        );

        foreach ($admins as $id) {
            $attr['id'] = 'il_' . $this->settings->get('inst_id') . '_usr_' . $id;
            $attr['notification'] = $this->participants->isNotificationEnabled($id) ? 'Yes' : 'No';

            $this->xmlElement('admin', $attr);
        }
    }

    public function __buildMember(): void
    {
        $members = $this->group_obj->getGroupMemberIds();
        $members = $this->access->filterUserIdsByRbacOrPositionOfCurrentUser(
            'manage_members',
            ilOrgUnitOperation::OP_MANAGE_MEMBERS,
            $this->group_obj->getRefId(),
            $members
        );
        foreach ($members as $id) {
            if (!$this->group_obj->isAdmin($id)) {
                $attr['id'] = 'il_' . $this->settings->get('inst_id') . '_usr_' . $id;

                $this->xmlElement('member', $attr);
            }
        }
    }

    public function __buildFooter(): void
    {
        $this->xmlEndTag('group');
    }

    public function setAttachUsers(bool $value)
    {
        $this->attach_users = $value;
    }
}
