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
 * Class ilInternalLink
 * Some methods to handle internal links
 * @author Alexander Killing <killing@leifos.de>
 */
class ilInternalLink
{
    /**
     * Delete all links of a given source
     */
    public static function _deleteAllLinksOfSource(
        string $a_source_type,
        int $a_source_id,
        string $a_lang = "-"
    ): void {
        global $DIC;

        $ilDB = $DIC->database();

        $lang_where = "";
        if ($a_lang !== "") {
            $lang_where = " AND source_lang = " . $ilDB->quote($a_lang, "text");
        }

        $q = "DELETE FROM int_link WHERE source_type = " .
            $ilDB->quote($a_source_type, "text") . " AND source_id=" .
            $ilDB->quote($a_source_id, "integer") .
            $lang_where;
        $ilDB->manipulate($q);
    }

    /**
     * Delete all links to a given target
     */
    public static function _deleteAllLinksToTarget(
        string $a_target_type,
        int $a_target_id,
        int $a_target_inst = 0
    ): void {
        global $DIC;

        $ilDB = $DIC->database();

        $ilDB->manipulateF(
            "DELETE FROM int_link WHERE target_type = %s " .
            " AND target_id = %s AND target_inst = %s ",
            array("text", "integer", "integer"),
            array($a_target_type, $a_target_id, $a_target_inst)
        );
    }

    /**
     * save internal link information
     */
    public static function _saveLink(
        string $a_source_type,
        int $a_source_id,
        string $a_target_type,
        int $a_target_id,
        int $a_target_inst = 0,
        string $a_source_lang = "-"
    ): void {
        global $DIC;

        $ilDB = $DIC->database();

        $ilDB->replace(
            "int_link",
            array(
                "source_type" => array("text", $a_source_type),
                "source_id" => array("integer", $a_source_id),
                "source_lang" => array("text", $a_source_lang),
                "target_type" => array("text", $a_target_type),
                "target_id" => array("integer", $a_target_id),
                "target_inst" => array("integer", $a_target_inst)
            ),
            array()
        );
    }

    /**
     * get all sources of a link target
     * @param	string		$a_target_type		target type
     * @param	int			$a_target_id		target id
     * @param	int			$a_target_inst		target installation id
     * @return	array		sources (array of array("type", "id"))
     */
    public static function _getSourcesOfTarget(
        string $a_target_type,
        int $a_target_id,
        int $a_target_inst
    ): array {
        global $DIC;

        $ilDB = $DIC->database();

        $q = "SELECT * FROM int_link WHERE " .
            "target_type = " . $ilDB->quote($a_target_type, "text") . " AND " .
            "target_id = " . $ilDB->quote($a_target_id, "integer") . " AND " .
            "target_inst = " . $ilDB->quote($a_target_inst, "integer");
        $source_set = $ilDB->query($q);
        $sources = array();
        while ($source_rec = $ilDB->fetchAssoc($source_set)) {
            $sources[$source_rec["source_type"] . ":" . $source_rec["source_id"] . ":" . $source_rec["source_lang"]] =
                array("type" => $source_rec["source_type"], "id" => $source_rec["source_id"],
                    "lang" => $source_rec["source_lang"]);
        }

        return $sources;
    }

    /**
     * Get all targets of a source object (e.g., a page)
     * @param	string		$a_source_type		source type (e.g. "lm:pg" | "dbk:pg")
     * @param	int			$a_source_id		source id
     * @param	string		$a_source_lang		source language
     * @return	array		targets (array of array("type", "id", "inst"))
     */
    public static function _getTargetsOfSource(
        string $a_source_type,
        int $a_source_id,
        string $a_source_lang = "-"
    ): array {
        global $DIC;

        $ilDB = $DIC->database();

        $lang_where = "";
        if ($a_source_lang !== "") {
            $lang_where = " AND source_lang = " . $ilDB->quote($a_source_lang, "text");
        }

        $q = "SELECT * FROM int_link WHERE " .
            "source_type = " . $ilDB->quote($a_source_type, "text") . " AND " .
            "source_id = " . $ilDB->quote($a_source_id, "integer") .
            $lang_where;

        $target_set = $ilDB->query($q);
        $targets = array();
        while ($target_rec = $ilDB->fetchAssoc($target_set)) {
            $targets[$target_rec["target_type"] . ":" . $target_rec["target_id"] . ":" . $target_rec["target_inst"]] =
                array("type" => $target_rec["target_type"], "id" => $target_rec["target_id"],
                "inst" => $target_rec["target_inst"]);
        }

        return $targets;
    }

    /**
     * Get current id for an import id
     * @param	string		$a_type			target type ("PageObject" | "StructureObject" |
     *										"GlossaryItem" | "MediaObject")
     * @param	string		$a_target		import target id (e.g. "il_2_pg_22")
     * @return	string		current target id (e.g. "il__pg_244")
     */
    public static function _getIdForImportId(
        string $a_type,
        string $a_target
    ): ?string {
        switch ($a_type) {
            case "PageObject":
                $id = ilLMObject::_getIdForImportId($a_target);
                if ($id > 0) {
                    return "il__pg_" . $id;
                }
                break;

            case "StructureObject":
                $id = ilLMObject::_getIdForImportId($a_target);
                if ($id > 0) {
                    return "il__st_" . $id;
                }
                break;

            case "GlossaryItem":
                $id = ilGlossaryTerm::_getIdForImportId($a_target);
                //echo "+".$id."+".$a_target."+";
                if ($id > 0) {
                    return "il__git_" . $id;
                }
                break;

            case "WikiPage":
                // no import IDs for wiki pages (yet)
                //$id = ilGlossaryTerm::_getIdForImportId($a_target);
                $id = 0;
                /*
                if ($id > 0) {
                    return "il__wpage_" . $id;
                }*/
                break;

            case "MediaObject":
                $id = ilObjMediaObject::_getIdForImportId($a_target);
                if ($id > 0) {
                    return "il__mob_" . $id;
                }
                break;

            case "RepositoryItem":

                $tarr = explode("_", $a_target);
                $import_id = $a_target;

                // if a ref id part is given, strip this
                // since this will not be part of an import id
                // see also bug #6685
                if ($tarr[4] != "") {
                    $import_id = $tarr[0] . "_" . $tarr[1] . "_" . $tarr[2] . "_" . $tarr[3];
                }

                $id = ilObject::_getIdForImportId($import_id);

                // get ref id for object id
                // (see ilPageObject::insertInstIntoIDs for the export procedure)
                if ($id > 0) {
                    $refs = ilObject::_getAllReferences($id);
                    foreach ($refs as $ref) {
                        return "il__obj_" . $ref;
                    }
                }

                // 26 Sep 2018: moved this under the import id handling above
                // If an imported object is found, this is always preferred.
                // see also bug #23324
                if (self::_extractInstOfTarget($a_target) == IL_INST_ID
                    && IL_INST_ID > 0) {
                    // does it have a ref id part?
                    if ($tarr[4] != "") {
                        return "il__obj_" . $tarr[4];
                    }
                }

                break;

        }
        return null;
    }

    /**
     * Check if internal link refers to a valid target
     * @param	string		$a_type			target type ("PageObject" | "StructureObject" |
     *										"GlossaryItem" | "MediaObject")
     * @param	string		$a_target		target id, e.g. "il__pg_244")
     * @return    bool        true/false
     */
    public static function _exists(
        string $a_type,
        string $a_target
    ): bool {
        global $DIC;

        $tree = $DIC->repositoryTree();

        switch ($a_type) {
            case "PageObject":
            case "StructureObject":
                return ilLMObject::_exists($a_target);

            case "GlossaryItem":
                return ilGlossaryTerm::_exists($a_target);

            case "MediaObject":
                return ilObjMediaObject::_exists($a_target);

            case "WikiPage":
                return ilWikiPage::_exists("wiki", (int) $a_target);

            case "RepositoryItem":
                if (is_int(strpos($a_target, "_"))) {
                    $ref_id = self::_extractObjIdOfTarget($a_target);
                    return $tree->isInTree($ref_id);
                }
                break;
        }
        return false;
    }


    /**
     * Extract installation id out of target
     * @param	string		$a_target		import target id (e.g. "il_2_pg_22")
     */
    public static function _extractInstOfTarget(string $a_target): ?int
    {
        if (!is_int(strpos($a_target, "__"))) {
            $target = explode("_", $a_target);
            if (isset($target[1]) && $target[1] > 0) {
                return (int) $target[1];
            }
        }
        return null;
    }

    /**
     * Removes installation id from target string
     * @param	string		$a_target		import target id (e.g. "il_2_pg_22")
     */
    public static function _removeInstFromTarget(string $a_target): ?string
    {
        if (!is_int(strpos($a_target, "__"))) {
            $target = explode("_", $a_target);
            if ($target[1] > 0) {
                return "il__" . $target[2] . "_" . $target[3];
            }
        }
        return null;
    }

    /**
     * Extract object id out of target
     * @param	string		$a_target		import target id (e.g. "il_2_pg_22")
     */
    public static function _extractObjIdOfTarget(string $a_target): int
    {
        $target = explode("_", $a_target);
        return (int) $target[count($target) - 1];
    }

    /**
     * Extract type out of target
     * @param	string		$a_target		import target id (e.g. "il_2_pg_22")
     */
    public static function _extractTypeOfTarget(string $a_target): string
    {
        $target = explode("_", $a_target);
        return (string) ($target[count($target) - 2] ?? "");
    }

    /**
     * Search users
     */
    public static function searchUsers(string $a_search_str): array
    {
        $result = new ilSearchResult();

        $query_parser = new ilQueryParser($a_search_str);
        $query_parser->setCombination(ilQueryParser::QP_COMBINATION_AND);
        $query_parser->setMinWordLength(3);
        $query_parser->parse();

        $user_search = ilObjectSearchFactory::_getUserSearchInstance($query_parser);
        $user_search->enableActiveCheck(true);
        $user_search->setFields(array('login'));
        $result_obj = $user_search->performSearch();
        $result->mergeEntries($result_obj);

        $user_search->setFields(array('firstname'));
        $result_obj = $user_search->performSearch();
        $result->mergeEntries($result_obj);

        $user_search->setFields(array('lastname'));
        $result_obj = $user_search->performSearch();
        $result->mergeEntries($result_obj);

        $result->setMaxHits(100000);
        $result->preventOverwritingMaxhits(true);
        $result->filter(ROOT_FOLDER_ID, true);

        // Filter users (depends on setting in user accounts)
        $users = ilUserFilter::getInstance()->filter($result->getResultIds());

        $p = ilObjUser::getProfileStatusOfUsers($users);

        $users = array_intersect($users, $p["public"]);

        return $users;
    }
}
