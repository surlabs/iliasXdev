<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\GlobalScreen\Scope\Layout\Provider\PagePart;

use ILIAS\GlobalScreen\Collector\Renderer\isSupportedTrait;
use ILIAS\GlobalScreen\isGlobalScreenItem;
use ILIAS\GlobalScreen\Scope\MainMenu\Collector\Renderer\SlateSessionStateCode;
use ILIAS\GlobalScreen\Scope\Tool\Factory\isToolItem;
use ILIAS\UI\Component\Breadcrumbs\Breadcrumbs;
use ILIAS\UI\Component\Image\Image;
use ILIAS\UI\Component\Legacy\Legacy;
use ILIAS\UI\Component\MainControls\Footer;
use ILIAS\UI\Component\MainControls\MainBar;
use ILIAS\UI\Component\MainControls\MetaBar;
use ILIAS\UI\Component\MainControls\Slate\Combined;
use ILIAS\UI\Component\Toast\Container as TContainer;
use ilUserUtil;
use ilUtil;
use ILIAS\GlobalScreen\Services;
use ILIAS\DI\UIServices;
use ilLanguage;
use ILIAS\GlobalScreen\Client\CallbackHandler;

/**
 * Class StandardPagePartProvider
 * @internal
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class StandardPagePartProvider implements PagePartProvider
{
    use isSupportedTrait;
    use SlateSessionStateCode;

    protected Legacy $content;
    protected Services $gs;
    protected UIServices $ui;
    protected ilLanguage $lang;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        global $DIC;
        $this->ui = $DIC->ui();
        $this->gs = $DIC->globalScreen();
        $this->lang = $DIC->language();
    }

    /**
     * @inheritDoc
     */
    public function getContent(): ?Legacy
    {
        return $this->content ?? $this->ui->factory()->legacy("");
    }

    /**
     * @inheritDoc
     */
    public function getMetaBar(): ?MetaBar
    {
        $this->gs->collector()->metaBar()->collectOnce();
        if (!$this->gs->collector()->metaBar()->hasItems()) {
            return null;
        }
        $f = $this->ui->factory();
        $meta_bar = $f->mainControls()->metaBar();

        foreach ($this->gs->collector()->metaBar()->getItemsForUIRepresentation() as $item) {
            /** @var $item isGlobalScreenItem */
            $component = $item->getRenderer()->getComponentForItem($item);
            if ($this->isComponentSupportedForCombinedSlate($component)) {
                $meta_bar = $meta_bar->withAdditionalEntry($item->getProviderIdentification()->getInternalIdentifier(), $component);
            }
        }

        return $meta_bar;
    }

    /**
     * @inheritDoc
     */
    public function getMainBar(): ?MainBar
    {
        $this->gs->collector()->mainmenu()->collectOnce();
        if (!$this->gs->collector()->mainmenu()->hasVisibleItems()
            && !$this->gs->collector()->tool()->hasVisibleItems()) {
            return null;
        }

        $f = $this->ui->factory();
        $main_bar = $f->mainControls()->mainBar();

        foreach ($this->gs->collector()->mainmenu()->getItemsForUIRepresentation() as $item) {
            /**
             * @var $component Combined
             */
            $component = $item->getTypeInformation()->getRenderer()->getComponentForItem($item, false);
            $identifier = $this->hash($item->getProviderIdentification()->serialize());

            if ($this->isComponentSupportedForCombinedSlate($component)) {
                $main_bar = $main_bar->withAdditionalEntry($identifier, $component);
            }
        }

        // Tools
        $grid_icon = $f->symbol()->icon()->custom(ilUtil::getImagePath("icon_tool.svg"), $this->lang->txt('more'));
        $this->gs->collector()->tool()->collectOnce();
        if ($this->gs->collector()->tool()->hasItems()) {
            $tools_button = $f->button()->bulky($grid_icon, $this->lang->txt('tools'), "#")->withEngagedState(true);
            $main_bar = $main_bar->withToolsButton($tools_button);
            /**
             * @var $main_bar MainBar
             */
            foreach ($this->gs->collector()->tool()->getItemsForUIRepresentation() as $tool) {
                /** @var $tool isToolItem */
                if (!$tool instanceof isToolItem) {
                    continue;
                }
                $component = $tool->getTypeInformation()->getRenderer()->getComponentForItem($tool, false);

                $identifier = $this->hash($tool->getProviderIdentification()->serialize());
                $close_button = null;
                if ($tool->hasCloseCallback()) {
                    $close_button = $this->ui->factory()->button()->close()->withOnLoadCode(static function (string $id) use ($identifier) {
                        $key_item = CallbackHandler::KEY_ITEM;
                        return "$('#$id').on('click', function(){
                            $.ajax({
                                url: 'src/GlobalScreen/Client/callback_handler.php?$key_item=$identifier'
                            }).done(function() {
                                console.log('done closing');
                            });
                        });";
                    });
                }
                $main_bar = $main_bar->withAdditionalToolEntry($identifier, $component, $tool->isInitiallyHidden(), $close_button);
            }
        }

        return $main_bar;
    }

    /**
     * @inheritDoc
     */
    public function getBreadCrumbs(): ?Breadcrumbs
    {
        // TODO this currently gets the items from ilLocatorGUI, should that serve be removed with
        // something like GlobalScreen\Scope\Locator\Item
        global $DIC;

        $f = $this->ui->factory();
        $crumbs = [];
        foreach ($DIC['ilLocator']->getItems() as $item) {
            if (empty($item['title']) || empty($item['link'])) {
                continue;
            }
            $crumbs[] = $f->link()->standard($item['title'], $item["link"]);
        }

        return $f->breadcrumbs($crumbs);
    }

    /**
     * @inheritDoc
     */
    public function getLogo(): ?Image
    {
        $std_logo = ilUtil::getImagePath("HeaderIcon.svg");

        return $this->ui->factory()->image()
                        ->standard($std_logo, $this->lang->txt('rep_main_page'))
                        ->withAction($this->getStartingPointAsUrl());
    }

    /**
     * @inheritDoc
     */
    public function getResponsiveLogo(): ?Image
    {
        $responsive_logo = ilUtil::getImagePath("HeaderIconResponsive.svg");

        return $this->ui->factory()->image()
                        ->standard($responsive_logo, $this->lang->txt('rep_main_page'))
                        ->withAction($this->getStartingPointAsUrl());
    }

    /**
     * @inheritDoc
     */
    public function getFaviconPath(): string
    {
        return ilUtil::getImagePath("favicon.ico");
    }

    protected function getStartingPointAsUrl(): string
    {
        $std_logo_link = ilUserUtil::getStartingPointAsUrl();
        if (!$std_logo_link) {
            $std_logo_link = "./goto.php?target=root_1";
        }
        return $std_logo_link;
    }

    /**
     * @inheritDoc
     */
    public function getSystemInfos(): array
    {
        $system_infos = [];

        foreach ($this->gs->collector()->notifications()->getAdministrativeNotifications() as $adn) {
            $system_infos[] = $adn->getRenderer($this->ui->factory())->getNotificationComponentForItem($adn);
        }

        return $system_infos;
    }

    /**
     * @inheritDoc
     */
    public function getFooter(): ?Footer
    {
        return $this->ui->factory()->mainControls()->footer([]);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'title';
    }

    /**
     * @inheritDoc
     */
    public function getShortTitle(): string
    {
        return 'short';
    }

    /**
     * @inheritDoc
     */
    public function getViewTitle(): string
    {
        return 'view';
    }

    /**
     * @inheritDoc
     */
    public function getToastContainer(): TContainer
    {
        $toast_container = $this->ui->factory()->toast()->container();

        foreach ($this->gs->collector()->toasts()->getToasts() as $toast) {
            $renderer = $toast->getRenderer();
            $toast_container = $toast_container->withAdditionalToast($renderer->getToastComponentForItem($toast));
        }

        return $toast_container;
    }
}
