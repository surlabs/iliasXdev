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

/**
 * Class ilAssErrorTextCorrections
 *
 * @author    Björn Heyser <info@bjoernheyser.de>
 * @version    $Id$
 *
 * @package    Modules/TestQuestionPool
 */
class ilAssErrorTextCorrectionsInputGUI extends ilErrorTextWizardInputGUI
{
    public function setValue($a_value): void
    {
        if (is_array($a_value)) {
            include_once "./Modules/TestQuestionPool/classes/class.assAnswerErrorText.php";
            if (is_array($a_value['points'])) {
                foreach ($this->values as $idx => $key) {
                    $this->values[$idx]->points = (
                        str_replace(",", ".", $a_value['points'][$idx])
                    );
                }
            }
        }
    }

    public function checkInput(): bool
    {
        global $DIC;
        $lng = $DIC['lng'];

        if (is_array($_POST[$this->getPostVar()])) {
            $_POST[$this->getPostVar()] = ilArrayUtil::stripSlashesRecursive($_POST[$this->getPostVar()]);
        }
        $foundvalues = $_POST[$this->getPostVar()];

        if (is_array($foundvalues)) {
            if (is_array($foundvalues['points'])) {
                foreach ($foundvalues['points'] as $val) {
                    if ($this->getRequired() && (strlen($val)) == 0) {
                        $this->setAlert($lng->txt("msg_input_is_required"));
                        return false;
                    }
                    if (!is_numeric(str_replace(",", ".", $val))) {
                        $this->setAlert($lng->txt("form_msg_numeric_value_required"));
                        return false;
                    }
                    if ((float) $val <= 0) {
                        $this->setAlert($lng->txt("positive_numbers_required"));
                        return false;
                    }
                }
            } else {
                if ($this->getRequired()) {
                    $this->setAlert($lng->txt("msg_input_is_required"));
                    return false;
                }
            }
        } else {
            if ($this->getRequired()) {
                $this->setAlert($lng->txt("msg_input_is_required"));
                return false;
            }
        }

        return $this->checkSubItemsInput();
    }

    public function insert(ilTemplate $a_tpl): void
    {
        global $DIC;
        $lng = $DIC['lng'];

        $tpl = new ilTemplate("tpl.prop_errortextcorrection_input.html", true, true, "Modules/TestQuestionPool");
        $i = 0;
        foreach ($this->values as $value) {
            $tpl->setCurrentBlock("prop_points_propval");
            $tpl->setVariable("PROPERTY_VALUE", ilLegacyFormElementsUtil::prepareFormOutput($value->points));
            $tpl->parseCurrentBlock();

            $tpl->setCurrentBlock("row");

            $tpl->setVariable("TEXT_WRONG", ilLegacyFormElementsUtil::prepareFormOutput($value->text_wrong));
            $tpl->setVariable("TEXT_CORRECT", ilLegacyFormElementsUtil::prepareFormOutput($value->text_correct));

            $class = ($i % 2 == 0) ? "even" : "odd";
            if ($i == 0) {
                $class .= " first";
            }
            if ($i == count($this->values) - 1) {
                $class .= " last";
            }
            $tpl->setVariable("ROW_CLASS", $class);
            $tpl->setVariable("ROW_NUMBER", $i);

            $tpl->setVariable("KEY_SIZE", $this->getKeySize());
            $tpl->setVariable("KEY_ID", $this->getPostVar() . "[key][$i]");
            $tpl->setVariable("KEY_MAXLENGTH", $this->getKeyMaxlength());

            $tpl->setVariable("VALUE_SIZE", $this->getValueSize());
            $tpl->setVariable("VALUE_ID", $this->getPostVar() . "[value][$i]");
            $tpl->setVariable("VALUE_MAXLENGTH", $this->getValueMaxlength());

            $tpl->setVariable("POST_VAR", $this->getPostVar());

            $tpl->parseCurrentBlock();

            $i++;
        }
        $tpl->setVariable("ELEMENT_ID", $this->getPostVar());
        $tpl->setVariable("KEY_TEXT", $this->getKeyName());
        $tpl->setVariable("VALUE_TEXT", $this->getValueName());
        $tpl->setVariable("POINTS_TEXT", $lng->txt('points'));

        $a_tpl->setCurrentBlock("prop_generic");
        $a_tpl->setVariable("PROP_GENERIC", $tpl->get());
        $a_tpl->parseCurrentBlock();
    }
}
