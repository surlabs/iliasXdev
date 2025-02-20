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
* Class for essay question exports
*
* assTextQuestionExport is a class for essay question exports
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version	$Id$
* @ingroup ModulesTestQuestionPool
*/
class assTextQuestionExport extends assQuestionExport
{
    /**
     * @var assTextQuestion
     */
    public $object;

    /**
    * Returns a QTI xml representation of the question
    * Returns a QTI xml representation of the question and sets the internal
    * domxml variable with the DOM XML representation of the QTI xml representation
    */
    public function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false, $force_image_references = false): string
    {
        global $DIC;
        $ilias = $DIC['ilias'];

        include_once("./Services/Xml/classes/class.ilXmlWriter.php");
        $a_xml_writer = new ilXmlWriter();
        // set xml header
        $a_xml_writer->xmlHeader();
        $a_xml_writer->xmlStartTag("questestinterop");
        $attrs = array(
            "ident" => "il_" . IL_INST_ID . "_qst_" . $this->object->getId(),
            "title" => $this->object->getTitle(),
            "maxattempts" => $this->object->getNrOfTries()
        );
        $a_xml_writer->xmlStartTag("item", $attrs);
        // add question description
        $a_xml_writer->xmlElement("qticomment", null, $this->object->getComment());
        // add estimated working time
        $workingtime = $this->object->getEstimatedWorkingTime();
        $duration = sprintf("P0Y0M0DT%dH%dM%dS", $workingtime["h"], $workingtime["m"], $workingtime["s"]);
        $a_xml_writer->xmlElement("duration", null, $duration);
        // add ILIAS specific metadata
        $a_xml_writer->xmlStartTag("itemmetadata");
        $a_xml_writer->xmlStartTag("qtimetadata");
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "ILIAS_VERSION");
        $a_xml_writer->xmlElement("fieldentry", null, $ilias->getSetting("ilias_version"));
        $a_xml_writer->xmlEndTag("qtimetadatafield");
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "QUESTIONTYPE");
        $a_xml_writer->xmlElement("fieldentry", null, TEXT_QUESTION_IDENTIFIER);
        $a_xml_writer->xmlEndTag("qtimetadatafield");
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "AUTHOR");
        $a_xml_writer->xmlElement("fieldentry", null, $this->object->getAuthor());
        $a_xml_writer->xmlEndTag("qtimetadatafield");

        // additional content editing information
        $this->addAdditionalContentEditingModeInformation($a_xml_writer);
        $this->addGeneralMetadata($a_xml_writer);

        $this->addQtiMetaDataField($a_xml_writer, 'wordcounter', (int) $this->object->isWordCounterEnabled());

        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "textrating");
        $a_xml_writer->xmlElement("fieldentry", null, $this->object->getTextRating());
        $a_xml_writer->xmlEndTag("qtimetadatafield");

        /*
        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", NULL, "keywords");
        $a_xml_writer->xmlElement("fieldentry", NULL, $this->object->getKeywords());
        $a_xml_writer->xmlEndTag("qtimetadatafield");
        */

        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "matchcondition");
        $a_xml_writer->xmlElement("fieldentry", null, $this->object->getMatchcondition());
        $a_xml_writer->xmlEndTag("qtimetadatafield");

        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "termscoring");
        $scores = base64_encode(serialize($this->object->getAnswers()));
        $a_xml_writer->xmlElement("fieldentry", null, $scores);
        $a_xml_writer->xmlEndTag("qtimetadatafield");

        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "termrelation");
        $a_xml_writer->xmlElement("fieldentry", null, $this->object->getKeywordRelation());
        $a_xml_writer->xmlEndTag("qtimetadatafield");

        $a_xml_writer->xmlStartTag("qtimetadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "specificfeedback");
        $a_xml_writer->xmlElement("fieldentry", null, $this->object->getKeywordRelation());
        $a_xml_writer->xmlEndTag("qtimetadatafield");

        $a_xml_writer->xmlEndTag("qtimetadata");
        $a_xml_writer->xmlEndTag("itemmetadata");

        // PART I: qti presentation
        $attrs = array(
            "label" => $this->object->getTitle()
        );
        $a_xml_writer->xmlStartTag("presentation", $attrs);
        // add flow to presentation
        $a_xml_writer->xmlStartTag("flow");
        // add material with question text to presentation
        $this->object->addQTIMaterial($a_xml_writer, $this->object->getQuestion());
        // add information on response rendering
        $attrs = array(
            "ident" => "TEXT",
            "rcardinality" => "Ordered"
        );
        $a_xml_writer->xmlStartTag("response_str", $attrs);
        $attrs = array(
            "fibtype" => "String",
            "prompt" => "Box"
        );
        if ($this->object->getMaxNumOfChars() > 0) {
            $attrs["maxchars"] = $this->object->getMaxNumOfChars();
        }
        $a_xml_writer->xmlStartTag("render_fib", $attrs);
        $attrs = array(
            "ident" => "A"
        );
        $a_xml_writer->xmlStartTag("response_label", $attrs);
        $a_xml_writer->xmlEndTag("response_label");
        $a_xml_writer->xmlEndTag("render_fib");

        $solution = $this->object->getSuggestedSolution(0);
        if ($solution !== null && count($solution)) {
            if (preg_match("/il_(\d*?)_(\w+)_(\d+)/", $solution["internal_link"], $matches)) {
                $a_xml_writer->xmlStartTag("material");
                $intlink = "il_" . IL_INST_ID . "_" . $matches[2] . "_" . $matches[3];
                if (strcmp($matches[1], "") != 0) {
                    $intlink = $solution["internal_link"];
                }
                $attrs = array(
                    "label" => "suggested_solution"
                );
                $a_xml_writer->xmlElement("mattext", $attrs, $intlink);
                $a_xml_writer->xmlEndTag("material");
            }
        }
        $a_xml_writer->xmlEndTag("response_str");
        $a_xml_writer->xmlEndTag("flow");
        $a_xml_writer->xmlEndTag("presentation");

        // PART II: qti resprocessing
        $attrs = array(
            "scoremodel" => "HumanRater"
        );
        $a_xml_writer->xmlStartTag("resprocessing", $attrs);
        $a_xml_writer->xmlStartTag("outcomes");
        $attrs = array(
            "varname" => "WritingScore",
            "vartype" => "Integer",
            "minvalue" => "0",
            "maxvalue" => $this->object->getPoints()
        );
        $a_xml_writer->xmlStartTag("decvar", $attrs);
        $a_xml_writer->xmlEndTag("decvar");
        $a_xml_writer->xmlEndTag("outcomes");

        $feedback_allcorrect = $this->object->feedbackOBJ->getGenericFeedbackExportPresentation(
            $this->object->getId(),
            true
        );
        $feedback_onenotcorrect = $this->object->feedbackOBJ->getGenericFeedbackExportPresentation(
            $this->object->getId(),
            false
        );
        if (strlen($feedback_allcorrect . $feedback_onenotcorrect)) {
            if (strlen($feedback_allcorrect)) {
                $attrs = array(
                    "continue" => "Yes"
                );
                $a_xml_writer->xmlStartTag("respcondition", $attrs);
                // qti conditionvar
                $a_xml_writer->xmlStartTag("conditionvar");
                $attrs = array(
                    "respident" => "points"
                );
                $a_xml_writer->xmlElement("varequal", $attrs, $this->object->getPoints());
                $a_xml_writer->xmlEndTag("conditionvar");
                // qti displayfeedback
                $attrs = array(
                    "feedbacktype" => "Response",
                    "linkrefid" => "response_allcorrect"
                );
                $a_xml_writer->xmlElement("displayfeedback", $attrs);
                $a_xml_writer->xmlEndTag("respcondition");
            }

            if (strlen($feedback_onenotcorrect)) {
                $attrs = array(
                    "continue" => "Yes"
                );
                $a_xml_writer->xmlStartTag("respcondition", $attrs);
                // qti conditionvar
                $a_xml_writer->xmlStartTag("conditionvar");
                $a_xml_writer->xmlStartTag("not");
                $attrs = array(
                    "respident" => "points"
                );
                $a_xml_writer->xmlElement("varequal", $attrs, $this->object->getPoints());
                $a_xml_writer->xmlEndTag("not");
                $a_xml_writer->xmlEndTag("conditionvar");
                // qti displayfeedback
                $attrs = array(
                    "feedbacktype" => "Response",
                    "linkrefid" => "response_onenotcorrect"
                );
                $a_xml_writer->xmlElement("displayfeedback", $attrs);
                $a_xml_writer->xmlEndTag("respcondition");
            }
        }

        $a_xml_writer->xmlStartTag("respcondition");
        $a_xml_writer->xmlStartTag("conditionvar");
        $a_xml_writer->xmlElement("other", null, "tutor_rated");
        $a_xml_writer->xmlEndTag("conditionvar");
        $a_xml_writer->xmlEndTag("respcondition");
        $a_xml_writer->xmlEndTag("resprocessing");

        $this->addAnswerSpecificFeedback($a_xml_writer, $this->object->feedbackOBJ->getAnswerOptionsByAnswerIndex());

        if (strlen($feedback_allcorrect)) {
            $attrs = array(
                "ident" => "response_allcorrect",
                "view" => "All"
            );
            $a_xml_writer->xmlStartTag("itemfeedback", $attrs);
            // qti flow_mat
            $a_xml_writer->xmlStartTag("flow_mat");
            $this->object->addQTIMaterial($a_xml_writer, $feedback_allcorrect);
            $a_xml_writer->xmlEndTag("flow_mat");
            $a_xml_writer->xmlEndTag("itemfeedback");
        }
        if (strlen($feedback_onenotcorrect)) {
            $attrs = array(
                "ident" => "response_onenotcorrect",
                "view" => "All"
            );
            $a_xml_writer->xmlStartTag("itemfeedback", $attrs);
            // qti flow_mat
            $a_xml_writer->xmlStartTag("flow_mat");
            $this->object->addQTIMaterial($a_xml_writer, $feedback_onenotcorrect);
            $a_xml_writer->xmlEndTag("flow_mat");
            $a_xml_writer->xmlEndTag("itemfeedback");
        }

        $a_xml_writer = $this->addSolutionHints($a_xml_writer);

        $a_xml_writer->xmlEndTag("item");
        $a_xml_writer->xmlEndTag("questestinterop");

        $xml = $a_xml_writer->xmlDumpMem(false);
        if (!$a_include_header) {
            $pos = strpos($xml, "?>");
            $xml = substr($xml, $pos + 2);
        }
        return $xml;
    }
}
