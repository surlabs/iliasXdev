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
 * SingleChoice survey question
 *
 * The SurveySingleChoiceQuestion class defines and encapsulates basic methods and attributes
 * for single choice survey question types.
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 */
class SurveySingleChoiceQuestion extends SurveyQuestion
{
    public SurveyCategories $categories;

    public function __construct(
        string $title = "",
        string $description = "",
        string $author = "",
        string $questiontext = "",
        int $owner = -1,
        int $orientation = 1
    ) {
        global $DIC;

        $this->db = $DIC->database();
        $this->user = $DIC->user();
        $this->lng = $DIC->language();
        parent::__construct($title, $description, $author, $questiontext, $owner);

        $this->orientation = $orientation;
        $this->categories = new SurveyCategories();
    }

    /**
     * Gets the available categories for a given phrase
     */
    public function getCategoriesForPhrase(int $phrase_id): array
    {
        $ilDB = $this->db;
        $categories = array();
        $result = $ilDB->queryF(
            "SELECT svy_category.* FROM svy_category, svy_phrase_cat WHERE svy_phrase_cat.category_fi = svy_category.category_id AND svy_phrase_cat.phrase_fi = %s ORDER BY svy_phrase_cat.sequence",
            array('integer'),
            array($phrase_id)
        );
        while ($row = $ilDB->fetchAssoc($result)) {
            if ((int) $row["defaultvalue"] === 1 && (int) $row["owner_fi"] === 0) {
                $categories[$row["category_id"]] = $this->lng->txt($row["title"]);
            } else {
                $categories[$row["category_id"]] = $row["title"];
            }
        }
        return $categories;
    }

    /**
     * Adds a phrase to the question
     */
    public function addPhrase(int $phrase_id): void
    {
        $ilUser = $this->user;
        $ilDB = $this->db;

        $result = $ilDB->queryF(
            "SELECT svy_category.* FROM svy_category, svy_phrase_cat WHERE svy_phrase_cat.category_fi = svy_category.category_id AND svy_phrase_cat.phrase_fi = %s AND (svy_category.owner_fi = 0 OR svy_category.owner_fi = %s) ORDER BY svy_phrase_cat.sequence",
            array('integer', 'integer'),
            array($phrase_id, $ilUser->getId())
        );
        while ($row = $ilDB->fetchAssoc($result)) {
            $neutral = $row["neutral"];
            if ((int) $row["defaultvalue"] === 1 && (int) $row["owner_fi"] === 0) {
                $this->categories->addCategory($this->lng->txt($row["title"]), 0, $neutral);
            } else {
                $this->categories->addCategory($row["title"], 0, $neutral);
            }
        }
    }

    public function getQuestionDataArray(int $id): array
    {
        $ilDB = $this->db;

        $result = $ilDB->queryF(
            "SELECT svy_question.*, " . $this->getAdditionalTableName() . ".* FROM svy_question, " . $this->getAdditionalTableName() . " WHERE svy_question.question_id = %s AND svy_question.question_id = " . $this->getAdditionalTableName() . ".question_fi",
            array('integer'),
            array($id)
        );
        if ($result->numRows() === 1) {
            return $ilDB->fetchAssoc($result);
        } else {
            return array();
        }
    }

    public function loadFromDb(int $question_id): void
    {
        $ilDB = $this->db;

        $result = $ilDB->queryF(
            "SELECT svy_question.*, " . $this->getAdditionalTableName() . ".* FROM svy_question LEFT JOIN " . $this->getAdditionalTableName() . " ON " . $this->getAdditionalTableName() . ".question_fi = svy_question.question_id WHERE svy_question.question_id = %s",
            array('integer'),
            array($question_id)
        );
        if ($result->numRows() === 1) {
            $data = $ilDB->fetchAssoc($result);
            $this->setId((int) $data["question_id"]);
            $this->setTitle((string) $data["title"]);
            $this->label = (string) $data['label'];
            $this->setDescription((string) $data["description"]);
            $this->setObjId((int) $data["obj_fi"]);
            $this->setAuthor((string) $data["author"]);
            $this->setOwner((int) $data["owner_fi"]);
            $this->setQuestiontext(ilRTE::_replaceMediaObjectImageSrc((string) $data["questiontext"], 1));
            $this->setObligatory((bool) $data["obligatory"]);
            $this->setComplete((bool) $data["complete"]);
            $this->setOriginalId((int) $data["original_id"]);
            $this->setOrientation((int) $data["orientation"]);

            $this->categories->flushCategories();
            $result = $ilDB->queryF(
                "SELECT svy_variable.*, svy_category.title, svy_category.neutral FROM svy_variable, svy_category WHERE svy_variable.question_fi = %s AND svy_variable.category_fi = svy_category.category_id ORDER BY sequence ASC",
                array('integer'),
                array($question_id)
            );
            if ($result->numRows() > 0) {
                while ($data = $ilDB->fetchAssoc($result)) {
                    $this->categories->addCategory($data["title"], $data["other"], $data["neutral"], null, ($data['scale']) ?: ($data['sequence'] + 1));
                }
            }
        }
        parent::loadFromDb($question_id);
    }

    public function isComplete(): bool
    {
        if (
            $this->getTitle() !== '' &&
            $this->getAuthor() !== '' &&
            $this->getQuestiontext() !== '' &&
            $this->categories->getCategoryCount()
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function saveToDb(int $original_id = 0): int
    {
        $ilDB = $this->db;

        $affectedRows = parent::saveToDb($original_id);
        if ($affectedRows === 1) {
            $this->log->debug("Before save Category-> DELETE from svy_qst_sc WHERE question_fi = " . $this->getId() . " AND INSERT again the same id and orientation in svy_qst_sc");
            $ilDB->manipulateF(
                "DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
                array('integer'),
                array($this->getId())
            );
            $ilDB->manipulateF(
                "INSERT INTO " . $this->getAdditionalTableName() . " (question_fi, orientation) VALUES (%s, %s)",
                array('integer', 'text'),
                array(
                    $this->getId(),
                    $this->getOrientation()
                )
            );

            $this->saveMaterial();
            $this->saveCategoriesToDb();
        }
        return $affectedRows;
    }

    public function saveCategoriesToDb(): void
    {
        $ilDB = $this->db;

        $this->log->debug("DELETE from svy_variable before the INSERT into svy_variable. if scale > 0  we get scale value else we get null");

        $affectedRows = $ilDB->manipulateF(
            "DELETE FROM svy_variable WHERE question_fi = %s",
            array('integer'),
            array($this->getId())
        );

        for ($i = 0; $i < $this->categories->getCategoryCount(); $i++) {
            $cat = $this->categories->getCategory($i);
            $category_id = $this->saveCategoryToDb($cat->title, $cat->neutral);
            $next_id = $ilDB->nextId('svy_variable');
            $affectedRows = $ilDB->manipulateF(
                "INSERT INTO svy_variable (variable_id, category_fi, question_fi, value1, other, sequence, scale, tstamp) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
                array('integer','integer','integer','float','integer','integer', 'integer','integer'),
                array($next_id, $category_id, $this->getId(), ($i + 1), $cat->other, $i, ($cat->scale > 0) ? $cat->scale : null, time())
            );

            $debug_scale = ($cat->scale > 0) ? $cat->scale : null;
            $this->log->debug("INSERT INTO svy_variable category_fi= " . $category_id . " question_fi= " . $this->getId() . " value1= " . ($i + 1) . " other= " . $cat->other . " sequence= " . $i . " scale =" . $debug_scale);
        }
        $this->saveCompletionStatus();
    }

    public function toXML(
        bool $a_include_header = true,
        bool $obligatory_state = false
    ): string {
        $a_xml_writer = new ilXmlWriter();
        $a_xml_writer->xmlHeader();
        $this->insertXML($a_xml_writer, $a_include_header);
        $xml = $a_xml_writer->xmlDumpMem(false);
        if (!$a_include_header) {
            $pos = strpos($xml, "?>");
            $xml = substr($xml, $pos + 2);
        }
        return $xml;
    }

    public function insertXML(
        ilXmlWriter $a_xml_writer,
        bool $a_include_header = true
    ): void {
        $attrs = array(
            "id" => $this->getId(),
            "title" => $this->getTitle(),
            "type" => $this->getQuestionType(),
            "obligatory" => $this->getObligatory()
        );
        $a_xml_writer->xmlStartTag("question", $attrs);

        $a_xml_writer->xmlElement("description", null, $this->getDescription());
        $a_xml_writer->xmlElement("author", null, $this->getAuthor());
        if (strlen($this->label)) {
            $attrs = array(
                "label" => $this->label,
            );
        } else {
            $attrs = array();
        }
        $a_xml_writer->xmlStartTag("questiontext", $attrs);
        $this->addMaterialTag($a_xml_writer, $this->getQuestiontext());
        $a_xml_writer->xmlEndTag("questiontext");

        $a_xml_writer->xmlStartTag("responses");

        for ($i = 0; $i < $this->categories->getCategoryCount(); $i++) {
            $attrs = array(
                "id" => $i
            );
            if (strlen($this->categories->getCategory($i)->other)) {
                $attrs['other'] = $this->categories->getCategory($i)->other;
            }
            if (strlen($this->categories->getCategory($i)->neutral)) {
                $attrs['neutral'] = $this->categories->getCategory($i)->neutral;
            }
            if (strlen($this->categories->getCategory($i)->label)) {
                $attrs['label'] = $this->categories->getCategory($i)->label;
            }
            if (strlen($this->categories->getCategory($i)->scale)) {
                $attrs['scale'] = $this->categories->getCategory($i)->scale;
            }
            $a_xml_writer->xmlStartTag("response_single", $attrs);
            $this->addMaterialTag($a_xml_writer, $this->categories->getCategory($i)->title);
            $a_xml_writer->xmlEndTag("response_single");
        }

        $a_xml_writer->xmlEndTag("responses");

        if (count($this->material)) {
            if (preg_match("/il_(\d*?)_(\w+)_(\d+)/", $this->material["internal_link"] ?? "", $matches)) {
                $attrs = array(
                    "label" => $this->material["title"]
                );
                $a_xml_writer->xmlStartTag("material", $attrs);
                $intlink = "il_" . IL_INST_ID . "_" . $matches[2] . "_" . $matches[3];
                if (strcmp($matches[1], "") != 0) {
                    $intlink = $this->material["internal_link"];
                }
                $a_xml_writer->xmlElement("mattext", null, $intlink);
                $a_xml_writer->xmlEndTag("material");
            }
        }

        $a_xml_writer->xmlStartTag("metadata");
        $a_xml_writer->xmlStartTag("metadatafield");
        $a_xml_writer->xmlElement("fieldlabel", null, "orientation");
        $a_xml_writer->xmlElement("fieldentry", null, $this->getOrientation());
        $a_xml_writer->xmlEndTag("metadatafield");
        $a_xml_writer->xmlEndTag("metadata");

        $a_xml_writer->xmlEndTag("question");
    }

    public function importAdditionalMetadata(array $a_meta): void
    {
        foreach ($a_meta as $key => $value) {
            switch ($value["label"]) {
                case "orientation":
                    $this->setOrientation($value["entry"]);
                    break;
            }
        }
    }

    /**
     * Adds standard numbers as categories
     */
    public function addStandardNumbers(
        int $lower_limit,
        int $upper_limit
    ): void {
        for ($i = $lower_limit; $i <= $upper_limit; $i++) {
            $this->categories->addCategory($i);
        }
    }

    /**
     * Saves a set of categories to a default phrase
     * note: data comes from session
     */
    public function savePhrase(string $title): void
    {
        $ilUser = $this->user;
        $ilDB = $this->db;

        $next_id = $ilDB->nextId('svy_phrase');
        $affectedRows = $ilDB->manipulateF(
            "INSERT INTO svy_phrase (phrase_id, title, defaultvalue, owner_fi, tstamp) VALUES (%s, %s, %s, %s, %s)",
            array('integer','text','text','integer','integer'),
            array($next_id, $title, 1, $ilUser->getId(), time())
        );
        $phrase_id = $next_id;

        $counter = 1;
        $phrase_data = $this->edit_manager->getPhraseData();
        foreach ($phrase_data as $data) {
            $next_id = $ilDB->nextId('svy_category');
            $affectedRows = $ilDB->manipulateF(
                "INSERT INTO svy_category (category_id, title, defaultvalue, owner_fi, tstamp, neutral) VALUES (%s, %s, %s, %s, %s, %s)",
                array('integer','text','text','integer','integer','text'),
                array($next_id, $data['answer'], 1, $ilUser->getId(), time(), $data['neutral'])
            );
            $category_id = $next_id;
            $next_id = $ilDB->nextId('svy_phrase_cat');
            $affectedRows = $ilDB->manipulateF(
                "INSERT INTO svy_phrase_cat (phrase_category_id, phrase_fi, category_fi, sequence, other, scale) VALUES (%s, %s, %s, %s, %s, %s)",
                array('integer', 'integer', 'integer','integer', 'integer', 'integer'),
                array($next_id, $phrase_id, $category_id, $counter, ($data['other']) ? 1 : 0, $data['scale'])
            );
            $counter++;
        }
    }

    public function getQuestionType(): string
    {
        return "SurveySingleChoiceQuestion";
    }

    public function getAdditionalTableName(): string
    {
        return "svy_qst_sc";
    }

    public function getWorkingDataFromUserInput(
        array $post_data
    ): array {
        $entered_value = $post_data[$this->getId() . "_value"] ?? "";
        $data = array();
        if (strlen($entered_value)) {
            $data[] = array("value" => $entered_value,
                            "textanswer" => $post_data[$this->getId() . '_' . $entered_value . '_other'] ?? ""
            );
        }
        for ($i = 0; $i < $this->categories->getCategoryCount(); $i++) {
            $cat = $this->categories->getCategory($i);
            if ($cat->other) {
                if ($i != $entered_value) {
                    if (strlen($post_data[$this->getId() . "_" . $i . "_other"] ?? "")) {
                        $data[] = array("value" => $i,
                                        "textanswer" => $post_data[$this->getId() . '_' . $i . '_other'] ?? "",
                                        "uncheck" => true
                        );
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Checks the input of the active user for obligatory status
     * and entered values
     */
    public function checkUserInput(
        array $post_data,
        int $survey_id
    ): string {
        $entered_value = $post_data[$this->getId() . "_value"] ?? "";

        $this->log->debug("Entered value = " . $entered_value);

        if ((!$this->getObligatory()) && (strlen($entered_value) == 0)) {
            return "";
        }

        if (strlen($entered_value) == 0) {
            return $this->lng->txt("question_not_checked");
        }

        for ($i = 0; $i < $this->categories->getCategoryCount(); $i++) {
            $cat = $this->categories->getCategory($i);
            if ($cat->other) {
                if ($i == $entered_value) {
                    if (array_key_exists($this->getId() . "_" . $entered_value . "_other", $post_data) && !strlen($post_data[$this->getId() . "_" . $entered_value . "_other"])) {
                        return $this->lng->txt("question_mr_no_other_answer");
                    }
                } elseif (strlen($post_data[$this->getId() . "_" . $i . "_other"] ?? "")) {
                    return $this->lng->txt("question_sr_no_other_answer_checked");
                }
            }
        }

        return "";
    }

    public function saveUserInput(
        array $post_data,
        int $active_id,
        bool $a_return = false
    ): ?array {
        $ilDB = $this->db;

        $entered_value = $post_data[$this->getId() . "_value"];

        if ($a_return) {
            return array(array("value" => $entered_value,
                "textanswer" => $post_data[$this->getId() . "_" . $entered_value . "_other"] ?? ""));
        }
        if (strlen($entered_value) == 0) {
            return null;
        }

        $next_id = $ilDB->nextId('svy_answer');
        #20216
        $fields = array();
        $fields['answer_id'] = array("integer", $next_id);
        $fields['question_fi'] = array("integer", $this->getId());
        $fields['active_fi'] = array("integer", $active_id);
        $fields['value'] = array("float", (strlen($entered_value)) ? $entered_value : null);
        $fields['textanswer'] = array("clob", isset($post_data[$this->getId() . "_" . $entered_value . "_other"]) ?
            $this->stripSlashesAddSpaceFallback($post_data[$this->getId() . "_" . $entered_value . "_other"]) : null);
        $fields['tstamp'] = array("integer", time());

        $affectedRows = $ilDB->insert("svy_answer", $fields);

        $debug_value = (strlen($entered_value)) ? $entered_value : "NULL";
        $debug_answer = $post_data[$this->getId() . "_" . $entered_value . "_other"] ?? "NULL";
        $this->log->debug("INSERT svy_answer answer_id=" . $next_id . " question_fi=" . $this->getId() . " active_fi=" . $active_id . " value=" . $debug_value . " textanswer=" . $debug_answer);
        return null;
    }

    public function importResponses(array $a_data): void
    {
        foreach ($a_data as $id => $data) {
            $categorytext = "";
            foreach ($data["material"] as $material) {
                $categorytext .= $material["text"];
            }
            $this->categories->addCategory(
                $categorytext,
                strlen($data['other']) ? $data['other'] : 0,
                strlen($data['neutral']) ? $data['neutral'] : 0,
                strlen($data['label']) ? $data['label'] : null,
                strlen($data['scale']) ? $data['scale'] : null
            );
        }
    }

    public function usableForPrecondition(): bool
    {
        return true;
    }

    public function getAvailableRelations(): array
    {
        return array("<", "<=", "=", "<>", ">=", ">");
    }

    public function getPreconditionOptions(): array
    {
        $options = array();
        for ($i = 0; $i < $this->categories->getCategoryCount(); $i++) {
            $category = $this->categories->getCategory($i);
            $options[$category->scale - 1] = $category->scale . " - " . $category->title;
        }
        return $options;
    }

    public function getPreconditionSelectValue(
        string $default,
        string $title,
        string $variable
    ): ?ilFormPropertyGUI {
        $step3 = new ilSelectInputGUI($title, $variable);
        $options = $this->getPreconditionOptions();
        $step3->setOptions($options);
        $step3->setValue($default);
        return $step3;
    }

    public function getPreconditionValueOutput(
        string $value
    ): string {
        // #18136
        $category = $this->categories->getCategoryForScale((int) $value + 1);

        // #17895 - see getPreconditionOptions()
        return $category->scale .
            " - " .
            ((strlen($category->title)) ? $category->title : $this->lng->txt('other_answer'));
    }

    public function getCategories(): SurveyCategories
    {
        return $this->categories;
    }

    public static function getMaxSumScore(int $survey_id): int
    {
        global $DIC;

        // we need max scale values of single choice questions (type 2)
        $db = $DIC->database();
        $set = $db->queryF(
            "SELECT SUM(max_sum_score) sum_sum_score FROM (SELECT MAX(scale) max_sum_score FROM svy_svy_qst sq " .
            "JOIN svy_question q ON (sq.question_fi = q.question_id) " .
            "JOIN svy_variable v ON (v.question_fi = q.question_id) " .
            "WHERE sq.survey_fi  = %s AND q.questiontype_fi = %s " .
            "GROUP BY (q.question_id)) x",
            ["integer", "integer"],
            [$survey_id, 2]
        );
        $rec = $db->fetchAssoc($set);
        return (int) $rec["sum_sum_score"];
    }

    protected function isSumScoreValid(int $nr_answer_records): bool
    {
        if ($nr_answer_records == 1) {
            return true;
        }
        return false;
    }

    public static function compressable(
        int $id1,
        int $id2
    ): bool {
        /** @var SurveySingleChoiceQuestion $q1 */
        $q1 = SurveyQuestion::_instanciateQuestion($id1);
        /** @var SurveySingleChoiceQuestion $q2 */
        $q2 = SurveyQuestion::_instanciateQuestion($id2);
        if ($q1->getOrientation() !== 1 || $q2->getOrientation() !== 1) {
            return false;
        }
        if (self::getCompressCompareString($q1) === self::getCompressCompareString($q2)) {
            return true;
        }
        return false;
    }

    public static function getCompressCompareString(
        SurveySingleChoiceQuestion $q
    ): string {
        $str = "";
        for ($i = 0; $i < $q->categories->getCategoryCount(); $i++) {
            $cat = $q->categories->getCategory($i);
            $str .= ":" . $cat->scale . ":" . $cat->title;
        }
        return $str;
    }
}
