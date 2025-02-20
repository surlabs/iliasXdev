<?php

declare(strict_types=1);
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

namespace ILIAS\GlobalScreen\Scope\MainMenu\Factory\TopItem;

use ILIAS\GlobalScreen\Scope\MainMenu\Factory\AbstractChildItem;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\hasAction;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\hasSymbol;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\hasSymbolTrait;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\hasTitle;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\isInterchangeableItem;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\isInterchangeableItemTrait;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\isTopItem;
use ILIAS\GlobalScreen\Scope\MainMenu\Factory\SymbolDecoratorTrait;

/**
 * Class TopLinkItem
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class TopLinkItem extends AbstractChildItem implements hasTitle, hasAction, isTopItem, hasSymbol, isInterchangeableItem
{
    use SymbolDecoratorTrait;
    use hasSymbolTrait;
    use isInterchangeableItemTrait;

    protected bool $is_external_action = false;

    protected string $title = '';

    protected string $action = '';

    /**
     * @param string $title
     * @return hasTitle|TopLinkItem
     */
    public function withTitle(string $title): hasTitle
    {
        $clone = clone($this);
        $clone->title = $title;

        return $clone;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $action
     * @return hasAction|TopLinkItem
     */
    public function withAction(string $action): hasAction
    {
        $clone = clone($this);
        $clone->action = $action;

        return $clone;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param bool $is_external
     * @return TopLinkItem
     */
    public function withIsLinkToExternalAction(bool $is_external): hasAction
    {
        $clone = clone $this;
        $clone->is_external_action = $is_external;

        return $clone;
    }

    /**
     * @return bool
     */
    public function isLinkWithExternalAction(): bool
    {
        return $this->is_external_action;
    }
}
