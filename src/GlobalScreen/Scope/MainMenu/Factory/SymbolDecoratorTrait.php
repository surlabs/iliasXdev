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

namespace ILIAS\GlobalScreen\Scope\MainMenu\Factory;

use Closure;
use ILIAS\UI\Component\Symbol\Symbol;
use LogicException;
use ReflectionFunction;
use ReflectionType;
use Throwable;

/**
 * Trait SymbolDecoratorTrait
 * @package ILIAS\GlobalScreen\Scope
 * @author  Fabian Schmid <fs@studer-raimann.ch>
 */
trait SymbolDecoratorTrait
{
    private ?Closure $symbol_decorator = null;

    /**
     * @param Closure $symbol_decorator
     * @return hasSymbol
     */
    public function addSymbolDecorator(Closure $symbol_decorator): hasSymbol
    {
        if (!$this->checkClosure($symbol_decorator)) {
            throw new LogicException('first argument of closure must be type-hinted to \ILIAS\UI\Component\Symbol\Symbol');
        }
        if ($this->symbol_decorator instanceof Closure) {
            $existing = $this->symbol_decorator;
            $this->symbol_decorator = static function (Symbol $c) use ($symbol_decorator, $existing): Symbol {
                $component = $existing($c);

                return $symbol_decorator($component);
            };
        } else {
            $this->symbol_decorator = $symbol_decorator;
        }

        return $this;
    }

    /**
     * @return Closure|null
     */
    public function getSymbolDecorator(): ?Closure
    {
        return $this->symbol_decorator;
    }

    private function checkClosure(Closure $c): bool
    {
        try {
            $r = new ReflectionFunction($c);
            if (count($r->getParameters()) !== 1) {
                return false;
            }
            $first_param_type = $r->getParameters()[0]->getType();
            if ($first_param_type instanceof ReflectionType && $first_param_type->getName() !== Symbol::class) {
                return false;
            }
            $return_type = $r->getReturnType();
            if ($return_type === null) {
                return false;
            }
            if ($return_type->getName() !== Symbol::class) {
                return false;
            }

            return true;
        } catch (Throwable $i) {
            return false;
        }
    }
}
