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
 * Exercise submission
 * //TODO: This class has many static methods related to delivered "files". Extract them to classes.
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @author Alexander Killing <killing@leifos.de>
 */
class ilExSubmission
{
    public const TYPE_FILE = "File";
    public const TYPE_OBJECT = "Object";	// Blogs in WSP/Portfolio
    public const TYPE_TEXT = "Text";
    public const TYPE_REPO_OBJECT = "RepoObject";	// Wikis

    protected ilObjUser $user;
    protected ilDBInterface $db;
    protected ilLanguage $lng;
    protected ilCtrl $ctrl;
    protected ilExAssignment $assignment;
    protected int $user_id;
    protected ?ilExAssignmentTeam $team = null;
    protected ?ilExPeerReview $peer_review = null;
    protected bool $is_tutor;
    protected bool $public_submissions;
    protected ilExAssignmentTypeInterface $ass_type;
    protected ilExAssignmentTypes $ass_types;
    protected ilExcAssMemberState $state;
    private \ilGlobalTemplateInterface $main_tpl;

    public function __construct(
        ilExAssignment $a_ass,
        int $a_user_id,
        ilExAssignmentTeam $a_team = null,
        bool $a_is_tutor = false,
        bool $a_public_submissions = false
    ) {
        global $DIC;
        $this->main_tpl = $DIC->ui()->mainTemplate();

        $this->user = $DIC->user();
        $this->db = $DIC->database();
        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();

        $this->assignment = $a_ass;
        $this->ass_type = $this->assignment->getAssignmentType();
        $this->ass_types = ilExAssignmentTypes::getInstance();

        $this->user_id = $a_user_id;
        $this->is_tutor = $a_is_tutor;
        $this->public_submissions = $a_public_submissions;

        $this->state = ilExcAssMemberState::getInstanceByIds($a_ass->getId(), $a_user_id);

        if ($a_ass->hasTeam()) {
            if (!$a_team) {
                $this->team = ilExAssignmentTeam::getInstanceByUserId($this->assignment->getId(), $this->user_id);
            } else {
                $this->team = $a_team;
            }
        }

        if ($this->assignment->getPeerReview()) {
            $this->peer_review = new ilExPeerReview($this->assignment);
        }
    }

    public function getSubmissionType(): string
    {
        return $this->assignment->getAssignmentType()->getSubmissionType();
    }

    public function getAssignment(): ilExAssignment
    {
        return $this->assignment;
    }

    public function getTeam(): ?ilExAssignmentTeam
    {
        return $this->team;
    }

    public function getPeerReview(): ?ilExPeerReview
    {
        return $this->peer_review;
    }

    public function validatePeerReviews(): array
    {
        $res = array();
        foreach ($this->getUserIds() as $user_id) {
            $valid = true;

            // no peer review == valid
            if ($this->peer_review) {
                $valid = $this->peer_review->isFeedbackValidForPassed($user_id);
            }

            $res[$user_id] = $valid;
        }
        return $res;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getUserIds(): array
    {
        if ($this->team &&
            !$this->hasNoTeamYet()) {
            return $this->team->getMembers();
        }

        // if has no team currently there still might be uploads attached
        return array($this->user_id);
    }

    public function getFeedbackId(): string
    {
        if ($this->team) {
            return "t" . $this->team->getId();
        } else {
            return (string) $this->getUserId();
        }
    }

    public function hasSubmitted(): bool
    {
        return (bool) count($this->getFiles(null, true));
    }

    public function hasSubmittedPrintVersion(): bool
    {
        return $this->getSubmittedPrintFile() !== "";
    }

    public function getSubmittedPrintFile(): string
    {
        $submitted = $this->getFiles(
            null,
            false,
            null,
            true
        );

        if (count($submitted) > 0) {
            $submitted = array_pop($submitted);

            if (is_file($submitted['filename'])) {
                return $submitted['filename'];
            }
        }

        return "";
    }

    public function getSelectedObject(): ?array
    {
        $files = $this->getFiles();
        if ($files !== []) {
            return array_pop($files);
        }
        return null;
    }

    public function canSubmit(): bool
    {
        return ($this->isOwner() &&
            $this->state->isSubmissionAllowed());
    }

    public function canView(): bool
    {
        $ilUser = $this->user;

        if ($this->canSubmit() ||
            $this->isTutor() ||
            $this->isInTeam() ||
            $this->public_submissions) {
            return true;
        }

        // #16115
        if ($this->peer_review) {
            // peer review givers may view peer submissions
            foreach ($this->peer_review->getPeerReviewsByPeerId($this->getUserId()) as $giver) {
                if ($giver["giver_id"] == $ilUser->getId()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isTutor(): bool
    {
        return $this->is_tutor;
    }

    public function hasNoTeamYet(): bool
    {
        if ($this->assignment->hasTeam() &&
            !$this->team->getId()) {
            return true;
        }
        return false;
    }

    public function isInTeam(int $a_user_id = null): bool
    {
        $ilUser = $this->user;

        if (!$a_user_id) {
            $a_user_id = $ilUser->getId();
        }
        return in_array($a_user_id, $this->getUserIds());
    }

    public function isOwner(): bool
    {
        $ilUser = $this->user;

        return ($ilUser->getId() == $this->getUserId());
    }

    public function hasPeerReviewAccess(): bool
    {
        return ($this->peer_review &&
            $this->peer_review->hasPeerReviewAccess($this->user_id));
    }

    public function canAddFile(): bool
    {
        if (!$this->canSubmit()) {
            return false;
        }

        $max = $this->getAssignment()->getMaxFile();
        if ($max &&
            $max <= sizeof($this->getFiles())) {
            return false;
        }

        return true;
    }


    //
    // FILES
    //

    protected function isLate(): bool
    {
        $dl = $this->state->getOfficialDeadline();
        //$dl = $this->assignment->getPersonalDeadline($this->getUserId());
        return ($dl && $dl < time());
    }

    protected function initStorage(): ilFSStorageExercise
    {
        return new ilFSStorageExercise($this->assignment->getExerciseId(), $this->assignment->getId());
    }

    protected function getStorageId(): int
    {
        if ($this->ass_type->isSubmissionAssignedToTeam()) {
            $storage_id = $this->getTeam()->getId();
        } else {
            $storage_id = $this->getUserId();
        }
        return $storage_id;
    }


    /**
     * Save submitted file of user
     * @throws ilFileUtilsException
     */
    public function uploadFile(
        array $a_http_post_files,
        bool $unzip = false
    ): bool {
        $ilDB = $this->db;

        if (!$this->canAddFile()) {
            return false;
        }

        if ($this->ass_type->isSubmissionAssignedToTeam()) {
            $team_id = $this->getTeam()->getId();
            $user_id = 0;
            if ($team_id == 0) {
                return false;
            }
        } else {
            $team_id = 0;
            $user_id = $this->getUserId();
        }
        $storage_id = $this->getStorageId();

        $deliver_result = $this->initStorage()->uploadFile($a_http_post_files, $storage_id, $unzip);

        if ($deliver_result) {
            $next_id = $ilDB->nextId("exc_returned");
            $query = sprintf(
                "INSERT INTO exc_returned " .
                             "(returned_id, obj_id, user_id, filename, filetitle, mimetype, ts, ass_id, late, team_id) " .
                             "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                $ilDB->quote($next_id, "integer"),
                $ilDB->quote($this->assignment->getExerciseId(), "integer"),
                $ilDB->quote($user_id, "integer"),
                $ilDB->quote($deliver_result["fullname"], "text"),
                $ilDB->quote(ilFileUtils::getValidFilename($a_http_post_files["name"]), "text"),
                $ilDB->quote($deliver_result["mimetype"], "text"),
                $ilDB->quote(ilUtil::now(), "timestamp"),
                $ilDB->quote($this->assignment->getId(), "integer"),
                $ilDB->quote($this->isLate(), "integer"),
                $ilDB->quote($team_id, "integer")
            );
            $ilDB->manipulate($query);

            if ($this->team) {
                $this->team->writeLog(
                    ilExAssignmentTeam::TEAM_LOG_ADD_FILE,
                    $a_http_post_files["name"]
                );
            }

            return true;
        }
        return false;
    }

    /**
     * processes error handling etc for uploaded archive
     * @param string $fileTmp path and filename to uploaded file
     */
    public function processUploadedZipFile(
        string $fileTmp
    ): bool {
        $lng = $this->lng;

        // Create unzip-directory
        $newDir = ilFileUtils::ilTempnam();
        ilFileUtils::makeDir($newDir);

        $success = true;

        try {
            $filearray = [];
            ilFileUtils::processZipFile($newDir, $fileTmp, false);
            ilFileUtils::recursive_dirscan($newDir, $filearray);

            // #18441 - check number of files in zip
            $max_num = $this->assignment->getMaxFile();
            if ($max_num) {
                $current_num = count($this->getFiles());
                $zip_num = count($filearray["file"]);
                if ($current_num + $zip_num > $max_num) {
                    $success = false;
                    $this->main_tpl->setOnScreenMessage('failure', $lng->txt("exc_upload_error") . " [Zip1]", true);
                }
            }

            if ($success) {
                foreach ($filearray["file"] as $key => $filename) {
                    $a_http_post_files["name"] = ilFileUtils::utf8_encode($filename);
                    $a_http_post_files["type"] = "other";
                    $a_http_post_files["tmp_name"] = $filearray["path"][$key] . "/" . $filename;
                    $a_http_post_files["error"] = 0;
                    $a_http_post_files["size"] = filesize($filearray["path"][$key] . "/" . $filename);

                    if (!$this->uploadFile($a_http_post_files, true)) {
                        $success = false;
                        $this->main_tpl->setOnScreenMessage('failure', $lng->txt("exc_upload_error") . " [Zip2]", true);
                    }
                }
            }
        } catch (ilFileUtilsException $e) {
            $success = false;
            $this->main_tpl->setOnScreenMessage('failure', $e->getMessage());
        }

        ilFileUtils::delDir($newDir);
        return $success;
    }

    /**
     * @throws ilExcUnknownAssignmentTypeException
     */
    public static function getAllAssignmentFiles(
        int $a_exc_id,
        int $a_ass_id
    ): array {
        global $DIC;

        $ilDB = $DIC->database();

        $storage = new ilFSStorageExercise($a_exc_id, $a_ass_id);
        $path = $storage->getAbsoluteSubmissionPath();

        $ass_type = ilExAssignmentTypes::getInstance()->getById(ilExAssignment::lookupType($a_ass_id));

        $query = "SELECT * FROM exc_returned WHERE ass_id = " .
            $ilDB->quote($a_ass_id, "integer");

        $res = $ilDB->query($query);
        $delivered = [];
        while ($row = $ilDB->fetchAssoc($res)) {
            if ($ass_type->isSubmissionAssignedToTeam()) {
                $storage_id = $row["team_id"];
            } else {
                $storage_id = $row["user_id"];
            }

            $row["timestamp"] = $row["ts"];
            $row["filename"] = $path . "/" . $storage_id . "/" . basename($row["filename"]);
            $delivered[] = $row;
        }

        return $delivered;
    }

    /**
     * @throws ilExcUnknownAssignmentTypeException
     */
    public static function getAssignmentFilesByUsers(
        int $a_exc_id,
        int $a_ass_id,
        array $a_users
    ): array {
        global $DIC;

        $ilDB = $DIC->database();

        $storage = new ilFSStorageExercise($a_exc_id, $a_ass_id);
        $path = $storage->getAbsoluteSubmissionPath();

        $ass_type = ilExAssignmentTypes::getInstance()->getById(ilExAssignment::lookupType($a_ass_id));

        $query = "SELECT * FROM exc_returned WHERE ass_id = " .
            $ilDB->quote($a_ass_id, "integer") .
            " AND user_id IN (" . implode(',', $a_users) . ")";

        $res = $ilDB->query($query);
        $delivered = [];
        while ($row = $ilDB->fetchAssoc($res)) {
            if ($ass_type->isSubmissionAssignedToTeam()) {
                $storage_id = $row["team_id"];
            } else {
                $storage_id = $row["user_id"];
            }

            $row["timestamp"] = $row["ts"];
            $row["filename"] = $path . "/" . $storage_id . "/" . basename($row["filename"]);
            $delivered[] = $row;
        }

        return $delivered;
    }

    /**
     * Get submission items (not only files)
     * @todo this also returns non-file entries, rename this, see dev.txt.php
     */
    public function getFiles(
        array $a_file_ids = null,
        bool $a_only_valid = false,
        string $a_min_timestamp = null,
        bool $print_versions = false
    ): array {
        $ilDB = $this->db;

        $sql = "SELECT * FROM exc_returned" .
            " WHERE ass_id = " . $ilDB->quote($this->getAssignment()->getId(), "integer");

        $sql .= " AND " . $this->getTableUserWhere(true);


        if ($a_file_ids) {
            $sql .= " AND " . $ilDB->in("returned_id", $a_file_ids, false, "integer");
        }

        if ($a_min_timestamp) {
            $sql .= " AND ts > " . $ilDB->quote($a_min_timestamp, "timestamp");
        }

        $result = $ilDB->query($sql);

        $delivered_files = array();
        if ($ilDB->numRows($result)) {
            $path = $this->initStorage()->getAbsoluteSubmissionPath();

            while ($row = $ilDB->fetchAssoc($result)) {
                // blog/portfolio/text submissions
                if ($a_only_valid &&
                    !$row["filename"] &&
                    !(trim($row["atext"]))) {
                    continue;
                }

                $row["owner_id"] = $row["user_id"];
                $row["timestamp"] = $row["ts"];
                $row["timestamp14"] = substr($row["ts"], 0, 4) .
                    substr($row["ts"], 5, 2) . substr($row["ts"], 8, 2) .
                    substr($row["ts"], 11, 2) . substr($row["ts"], 14, 2) .
                    substr($row["ts"], 17, 2);

                if ($this->getAssignment()->getAssignmentType()->isSubmissionAssignedToTeam()) {
                    $storage_id = $row["team_id"];
                } else {
                    $storage_id = $row["user_id"];
                }


                $row["filename"] = $path .
                    "/" . $storage_id . "/" . basename($row["filename"]);

                // see 22301, 22719
                if (is_file($row["filename"]) || (!$this->assignment->getAssignmentType()->usesFileUpload())) {
                    $delivered_files[] = $row;
                }
            }
        }

        // filter print versions
        if (in_array($this->assignment->getType(), [
            ilExAssignment::TYPE_BLOG,
            ilExAssignment::TYPE_PORTFOLIO,
            ilExAssignment::TYPE_WIKI_TEAM
        ])) {
            $delivered_files = array_filter($delivered_files, function ($i) use ($print_versions) {
                $is_print_version = false;
                if (substr($i["filetitle"], strlen($i["filetitle"]) - 5) == "print") {
                    $is_print_version = true;
                }
                if (substr($i["filetitle"], strlen($i["filetitle"]) - 9) == "print.zip") {
                    $is_print_version = true;
                }
                return ($is_print_version == $print_versions);
            });
        }

        return $delivered_files;
    }

    /**
     * Check how much files have been uploaded by the learner
     * after the last download of the tutor.
     */
    public function lookupNewFiles(
        int $a_tutor = null
    ): array {
        $ilDB = $this->db;
        $ilUser = $this->user;

        $tutor = ($a_tutor)
            ?: $ilUser->getId();

        $where = " AND " . $this->getTableUserWhere(true);

        $q = "SELECT exc_returned.returned_id AS id " .
            "FROM exc_usr_tutor, exc_returned " .
            "WHERE exc_returned.ass_id = exc_usr_tutor.ass_id " .
            " AND exc_returned.user_id = exc_usr_tutor.usr_id " .
            " AND exc_returned.ass_id = " . $ilDB->quote($this->getAssignment()->getId(), "integer") .
            $where .
            " AND exc_usr_tutor.tutor_id = " . $ilDB->quote($tutor, "integer") .
            " AND exc_usr_tutor.download_time < exc_returned.ts ";

        $new_up_set = $ilDB->query($q);

        $new_up = array();
        while ($new_up_rec = $ilDB->fetchAssoc($new_up_set)) {
            $new_up[] = $new_up_rec["id"];
        }

        return $new_up;
    }

    /**
     * Get exercise from submission id (used in ilObjMediaObject)
     */
    public static function lookupExerciseIdForReturnedId(
        int $a_returned_id
    ): int {
        global $DIC;

        $ilDB = $DIC->database();

        $set = $ilDB->query("SELECT obj_id" .
            " FROM exc_returned" .
            " WHERE returned_id = " . $ilDB->quote($a_returned_id, "integer"));
        $row = $ilDB->fetchAssoc($set);
        return (int) $row["obj_id"];
    }

    /**
     * Check if given file was assigned
     * Used in Blog/Portfolio
     */
    public static function findUserFiles(
        int $a_user_id,
        string $a_filetitle
    ): array {
        global $DIC;

        $ilDB = $DIC->database();

        $set = $ilDB->query("SELECT obj_id, ass_id" .
            " FROM exc_returned" .
            " WHERE user_id = " . $ilDB->quote($a_user_id, "integer") .
            " AND filetitle = " . $ilDB->quote($a_filetitle, "text"));
        $res = array();
        while ($row = $ilDB->fetchAssoc($set)) {
            $res[$row["ass_id"]] = $row;
        }
        return $res;
    }

    public function deleteAllFiles(): void
    {
        $files = array();
        // normal files
        foreach ($this->getFiles() as $item) {
            $files[] = $item["returned_id"];
        }
        // print versions
        foreach ($this->getFiles(null, false, null, true) as $item) {
            $files[] = $item["returned_id"];
        }
        if ($files !== []) {
            $this->deleteSelectedFiles($files);
        }
    }

    /**
    * Deletes already delivered files
    * @param array $file_id_array An array containing database ids of the delivered files
    */
    public function deleteSelectedFiles(
        array $file_id_array
    ): void {
        $ilDB = $this->db;


        $where = " AND " . $this->getTableUserWhere(true);


        if ($file_id_array === []) {
            return;
        }

        if ($file_id_array !== []) {
            $result = $ilDB->query("SELECT * FROM exc_returned" .
                " WHERE " . $ilDB->in("returned_id", $file_id_array, false, "integer") .
                $where);

            if ($ilDB->numRows($result)) {
                $result_array = array();
                while ($row = $ilDB->fetchAssoc($result)) {
                    $row["timestamp"] = $row["ts"];
                    $result_array[] = $row;
                }

                // delete the entries in the database
                $ilDB->manipulate("DELETE FROM exc_returned" .
                    " WHERE " . $ilDB->in("returned_id", $file_id_array, false, "integer") .
                    $where);

                // delete the files
                $path = $this->initStorage()->getAbsoluteSubmissionPath();
                foreach ($result_array as $value) {
                    if ($value["filename"]) {
                        if ($this->team) {
                            $this->team->writeLog(
                                ilExAssignmentTeam::TEAM_LOG_REMOVE_FILE,
                                $value["filetitle"]
                            );
                        }

                        if ($this->getAssignment()->getAssignmentType()->isSubmissionAssignedToTeam()) {
                            $storage_id = $value["team_id"];
                        } else {
                            $storage_id = $value["user_id"];
                        }

                        $filename = $path . "/" . $storage_id . "/" . basename($value["filename"]);
                        if (file_exists($filename)) {
                            unlink($filename);
                        }
                    }
                }
            }
        }
    }

    /**
     * Delete all delivered files of user
     * @throws ilExcUnknownAssignmentTypeException
     */
    public static function deleteUser(
        int $a_exc_id,
        int $a_user_id
    ): void {
        foreach (ilExAssignment::getInstancesByExercise($a_exc_id) as $ass) {
            $submission = new self($ass, $a_user_id);
            $submission->deleteAllFiles();

            // remove from any team
            $team = $submission->getTeam();
            if ($team) {
                $team->removeTeamMember($a_user_id);
            }

            // #14900
            $member_status = $ass->getMemberStatus($a_user_id);
            $member_status->setStatus("notgraded");
            $member_status->update();
        }
    }

    /**
     * @param array $a_user_ids
     * @return string "Y-m-d H:i:s"
     */
    protected function getLastDownloadTime(
        array $a_user_ids
    ): string {
        $ilDB = $this->db;
        $ilUser = $this->user;

        $q = "SELECT download_time FROM exc_usr_tutor WHERE " .
            " ass_id = " . $ilDB->quote($this->getAssignment()->getId(), "integer") . " AND " .
            $ilDB->in("usr_id", $a_user_ids, "", "integer") . " AND " .
            " tutor_id = " . $ilDB->quote($ilUser->getId(), "integer") .
            " ORDER BY download_time DESC";
        $lu_set = $ilDB->query($q);
        $lu_rec = $ilDB->fetchAssoc($lu_set);
        return $lu_rec["download_time"];
    }

    public function downloadFiles(
        array $a_file_ids = null,
        bool $a_only_new = false,
        bool $a_peer_review_mask_filename = false
    ): bool {
        $ilUser = $this->user;
        $lng = $this->lng;

        $user_ids = $this->getUserIds();
        $is_team = $this->assignment->hasTeam();
        // get last download time
        $download_time = null;
        if ($a_only_new) {
            $download_time = $this->getLastDownloadTime($user_ids);
        }

        if ($this->is_tutor) {
            $this->updateTutorDownloadTime();
        }

        if ($a_peer_review_mask_filename) {
            // process peer review sequence id
            $peer_id = null;
            foreach ($this->peer_review->getPeerReviewsByGiver($ilUser->getId()) as $idx => $item) {
                if ($item["peer_id"] == $this->getUserId()) {
                    $peer_id = $idx + 1;
                    break;
                }
            }
            // this will remove personal info from zip-filename
            $is_team = true;
        }

        $files = $this->getFiles($a_file_ids, false, $download_time);

        if ($files !== []) {
            if (count($files) == 1) {
                $file = array_pop($files);

                switch ($this->assignment->getType()) {
                    case ilExAssignment::TYPE_BLOG:
                    case ilExAssignment::TYPE_PORTFOLIO:
                        $file["filetitle"] = ilObjUser::_lookupName($file["user_id"]);
                        $file["filetitle"] = ilObject::_lookupTitle($this->assignment->getExerciseId()) . " - " .
                            $this->assignment->getTitle() . " - " .
                            $file["filetitle"]["firstname"] . " " .
                            $file["filetitle"]["lastname"] . " (" .
                            $file["filetitle"]["login"] . ").zip";
                        break;

                    // @todo: generalize
                    case ilExAssignment::TYPE_WIKI_TEAM:
                        $file["filetitle"] = ilObject::_lookupTitle($this->assignment->getExerciseId()) . " - " .
                            $this->assignment->getTitle() . " (Team " . $this->getTeam()->getId() . ").zip";
                        break;

                    default:
                        break;
                }

                if ($a_peer_review_mask_filename) {
                    $title_a = explode(".", $file["filetitle"]);
                    $suffix = array_pop($title_a);
                    $file["filetitle"] = $this->assignment->getTitle() . "_peer" . $peer_id . "." . $suffix;
                } elseif ($file["late"]) {
                    $file["filetitle"] = $lng->txt("exc_late_submission") . " - " .
                        $file["filetitle"];
                }

                $this->downloadSingleFile($file["user_id"], $file["filename"], $file["filetitle"], $file["team_id"]);
            } else {
                $array_files = array();
                foreach ($files as $seq => $file) {
                    if ($this->assignment->getAssignmentType()->isSubmissionAssignedToTeam()) {
                        $storage_id = $file["team_id"];
                    } else {
                        $storage_id = $file["user_id"];
                    }

                    $src = basename($file["filename"]);
                    if ($a_peer_review_mask_filename) {
                        $src_a = explode(".", $src);
                        $suffix = array_pop($src_a);
                        $tgt = $this->assignment->getTitle() . "_peer" . $peer_id .
                            "_" . (++$seq) . "." . $suffix;

                        $array_files[$storage_id][] = array(
                            "src" => $src,
                            "tgt" => $tgt
                        );
                    } else {
                        $array_files[$storage_id][] = array(
                            "src" => $src,
                            "late" => $file["late"]
                        );
                    }
                }
                $this->downloadMultipleFiles(
                    $array_files,
                    ($is_team ? null : $this->getUserId()),
                    $is_team
                );
            }
        } else {
            return false;
        }

        return true;
    }

    // Update the timestamp of the last download of current user (=tutor)
    public function updateTutorDownloadTime(): void
    {
        $ilUser = $this->user;
        $ilDB = $this->db;

        $exc_id = $this->assignment->getExerciseId();
        $ass_id = $this->assignment->getId();

        foreach ($this->getUserIds() as $user_id) {
            $ilDB->manipulateF(
                "DELETE FROM exc_usr_tutor " .
                "WHERE ass_id = %s AND usr_id = %s AND tutor_id = %s",
                array("integer", "integer", "integer"),
                array($ass_id, $user_id, $ilUser->getId())
            );

            $ilDB->manipulateF(
                "INSERT INTO exc_usr_tutor (ass_id, obj_id, usr_id, tutor_id, download_time) VALUES " .
                "(%s, %s, %s, %s, %s)",
                array("integer", "integer", "integer", "integer", "timestamp"),
                array($ass_id, $exc_id, $user_id, $ilUser->getId(), ilUtil::now())
            );
        }
    }

    protected function downloadSingleFile(
        int $a_user_id,
        string $filename,
        string $filetitle,
        int $a_team_id = 0
    ): void {
        if ($this->ass_type->isSubmissionAssignedToTeam()) {
            $storage_id = $a_team_id;
        } else {
            $storage_id = $a_user_id;
        }

        $filename = $this->initStorage()->getAbsoluteSubmissionPath() .
            "/" . $storage_id . "/" . basename($filename);

        ilFileDelivery::deliverFileLegacy($filename, $filetitle);
    }

    protected function downloadMultipleFiles(
        array $a_filenames,
        ?int $a_user_id,
        bool $a_multi_user = false
    ): void {
        $lng = $this->lng;
        $a_user_id = (int) $a_user_id;

        $path = $this->initStorage()->getAbsoluteSubmissionPath();

        $cdir = getcwd();

        $zip = PATH_TO_ZIP;
        $tmpdir = ilFileUtils::ilTempnam();
        $tmpfile = ilFileUtils::ilTempnam();
        $tmpzipfile = $tmpfile . ".zip";

        ilFileUtils::makeDir($tmpdir);
        chdir($tmpdir);

        $assTitle = ilExAssignment::lookupTitle($this->assignment->getId());
        $deliverFilename = str_replace(" ", "_", $assTitle);
        if ($a_user_id > 0 && !$a_multi_user) {
            $userName = ilObjUser::_lookupName($a_user_id);
            $deliverFilename .= "_" . $userName["lastname"] . "_" . $userName["firstname"];
        } else {
            $deliverFilename .= "_files";
        }
        $orgDeliverFilename = trim($deliverFilename);
        $deliverFilename = ilFileUtils::getASCIIFilename($orgDeliverFilename);
        ilFileUtils::makeDir($tmpdir . "/" . $deliverFilename);
        chdir($tmpdir . "/" . $deliverFilename);

        //copy all files to a temporary directory and remove them afterwards
        $parsed_files = $duplicates = array();
        foreach ($a_filenames as $storage_id => $files) {
            $pathname = $path . "/" . $storage_id;

            foreach ($files as $filename) {
                // peer review masked filenames, see deliverReturnedFiles()
                if (isset($filename["tgt"])) {
                    $newFilename = $filename["tgt"];
                    $filename = $filename["src"];
                } else {
                    $late = $filename["late"];
                    $filename = $filename["src"];

                    // remove timestamp
                    $newFilename = trim($filename);
                    $pos = strpos($newFilename, "_");
                    if ($pos !== false) {
                        $newFilename = substr($newFilename, $pos + 1);
                    }
                    // #11070
                    $chkName = strtolower($newFilename);
                    if (array_key_exists($chkName, $duplicates)) {
                        $suffix = strrpos($newFilename, ".");
                        $newFilename = substr($newFilename, 0, $suffix) .
                            " (" . (++$duplicates[$chkName]) . ")" .
                            substr($newFilename, $suffix);
                    } else {
                        $duplicates[$chkName] = 1;
                    }

                    if ($late) {
                        $newFilename = $lng->txt("exc_late_submission") . " - " .
                            $newFilename;
                    }
                }

                $newFilename = ilFileUtils::getASCIIFilename($newFilename);
                $newFilename = $tmpdir . DIRECTORY_SEPARATOR . $deliverFilename . DIRECTORY_SEPARATOR . $newFilename;
                // copy to temporal directory
                $oldFilename = $pathname . DIRECTORY_SEPARATOR . $filename;
                if (!copy($oldFilename, $newFilename)) {
                    echo 'Could not copy ' . $oldFilename . ' to ' . $newFilename;
                }
                touch($newFilename, filectime($oldFilename));
                $parsed_files[] = ilShellUtil::escapeShellArg(
                    $deliverFilename . DIRECTORY_SEPARATOR . basename($newFilename)
                );
            }
        }

        chdir($tmpdir);
        $zipcmd = $zip . " " . ilShellUtil::escapeShellArg($tmpzipfile) . " " . implode(" ", $parsed_files);

        exec($zipcmd);
        ilFileUtils::delDir($tmpdir);

        chdir($cdir);
        ilFileDelivery::deliverFileLegacy($tmpzipfile, $orgDeliverFilename . ".zip", "", false, true);
        exit;
    }

    /**
     * Download all submitted files of an assignment (all user)
     * @throws ilExerciseException
     */
    public static function downloadAllAssignmentFiles(
        ilExAssignment $a_ass,
        array $members,
        string $to_path
    ): void {
        global $DIC;

        $lng = $DIC->language();

        $storage = new ilFSStorageExercise($a_ass->getExerciseId(), $a_ass->getId());
        $storage->create();

        ksort($members);
        //$savepath = $this->getExercisePath() . "/" . $this->obj_id . "/";
        $savepath = $storage->getAbsoluteSubmissionPath();
        $cdir = getcwd();


        // important check: if the directory does not exist
        // ILIAS stays in the current directory (echoing only a warning)
        // and the zip command below archives the whole ILIAS directory
        // (including the data directory) and sends a mega file to the user :-o
        if (!is_dir($savepath)) {
            return;
        }
        // Safe mode fix
        //		chdir($this->getExercisePath());

        $tmpdir = $storage->getTempPath();
        chdir($tmpdir);
        $zip = PATH_TO_ZIP;

        // check free diskspace
        $dirsize = 0;
        foreach (array_keys($members) as $id) {
            $directory = $savepath . DIRECTORY_SEPARATOR . $id;
            $dirsize += ilFileUtils::dirsize($directory);
        }
        if ($dirsize > disk_free_space($tmpdir)) {
            return;
        }

        $ass_type = $a_ass->getType();

        // copy all member directories to the temporary folder
        // switch from id to member name and append the login if the member name is double
        // ensure that no illegal filenames will be created
        // remove timestamp from filename
        $team_map = null;
        $team_dirs = null;
        if ($a_ass->hasTeam()) {
            $team_dirs = array();
            $team_map = ilExAssignmentTeam::getAssignmentTeamMap($a_ass->getId());
        }
        foreach ($members as $id => $item) {
            $user_files = $item["files"] ?? null;
            $sourcedir = $savepath . DIRECTORY_SEPARATOR . $id;
            if (!is_dir($sourcedir)) {
                continue;
            }

            // group by teams
            $team_dir = "";
            if (is_array($team_map) &&
                array_key_exists($id, $team_map)) {
                $team_id = $team_map[$id];
                if (!array_key_exists($team_id, $team_dirs)) {
                    $team_dir = $lng->txt("exc_team") . " " . $team_id;
                    ilFileUtils::makeDir($team_dir);
                    $team_dirs[$team_id] = $team_dir;
                }
                $team_dir = $team_dirs[$team_id] . DIRECTORY_SEPARATOR;
            }

            if ($a_ass->getAssignmentType()->isSubmissionAssignedToTeam()) {
                $targetdir = $team_dir . ilFileUtils::getASCIIFilename(
                    $item["name"]
                );
                if ($targetdir == "") {
                    continue;
                }
            } else {
                $targetdir = self::getDirectoryNameFromUserData($id);
                if ($a_ass->getAssignmentType()->usesTeams()) {
                    $targetdir = $team_dir . $targetdir;
                }
            }
            ilFileUtils::makeDir($targetdir);

            $sourcefiles = scandir($sourcedir);
            $duplicates = array();
            foreach ($sourcefiles as $sourcefile) {
                if ($sourcefile == "." || $sourcefile == "..") {
                    continue;
                }

                $targetfile = trim(basename($sourcefile));
                $pos = strpos($targetfile, "_");
                if ($pos !== false) {
                    $targetfile = substr($targetfile, $pos + 1);
                }

                if ($a_ass->getAssignmentType()->getSubmissionType() == self::TYPE_REPO_OBJECT) {
                    $obj_id = ilObject::_lookupObjId($targetfile);
                    $obj_type = ilObject::_lookupType($obj_id);
                    $targetfile = $obj_type . "_" . $obj_id . ".zip";
                }


                // #14536
                if (array_key_exists($targetfile, $duplicates)) {
                    $suffix = strrpos($targetfile, ".");
                    $targetfile = substr($targetfile, 0, $suffix) .
                        " (" . (++$duplicates[$targetfile]) . ")" .
                        substr($targetfile, $suffix);
                } else {
                    $duplicates[$targetfile] = 1;
                }

                // late submission?
                if (isset($user_files)) {	// see #23900
                    foreach ($user_files as $file) {
                        if (basename($file["filename"]) == $sourcefile) {
                            if ($file["late"]) {
                                $targetfile = $lng->txt("exc_late_submission") . " - " .
                                    $targetfile;
                            }
                            break;
                        }
                    }
                }

                $targetfile = ilFileUtils::getASCIIFilename($targetfile);
                $targetfile = $targetdir . DIRECTORY_SEPARATOR . $targetfile;
                $sourcefile = $sourcedir . DIRECTORY_SEPARATOR . $sourcefile;

                if (!copy($sourcefile, $targetfile)) {
                    throw new ilExerciseException("Could not copy " . basename($sourcefile) . " to '" . $targetfile . "'.");
                } else {
                    // preserve time stamp
                    touch($targetfile, filectime($sourcefile));

                    // blogs and portfolios are stored as zip and have to be unzipped
                    if ($ass_type == ilExAssignment::TYPE_PORTFOLIO ||
                        $ass_type == ilExAssignment::TYPE_BLOG) {
                        ilFileUtils::unzip($targetfile);
                        unlink($targetfile);
                    }
                }
            }
        }
        $tmpzipfile = ilFileUtils::getASCIIFilename($lng->txt("exc_ass_submission_zip")) . ".zip";
        // Safe mode fix
        $zipcmd = $zip . " -r " . ilShellUtil::escapeShellArg($tmpzipfile) . " .";
        exec($zipcmd);
        //$path_final_zip_file = $to_path.DIRECTORY_SEPARATOR."Submissions/".$tmpzipfile;
        $path_final_zip_file = $to_path . DIRECTORY_SEPARATOR . $tmpzipfile;

        if (file_exists($tmpdir . DIRECTORY_SEPARATOR . $tmpzipfile)) {
            copy($tmpzipfile, $path_final_zip_file);
            ilFileUtils::delDir($tmpdir);

            //unzip the submissions zip file.(decided to unzip to allow the excel link the files more obvious when blog/portfolio)
            chdir($to_path);
            //TODO Bug in ilUtil -> if flat unzip fails. We can get rid of creating Submissions directory
            //ilUtil::unzip($path_final_zip_file,FALSE, TRUE);
            ilFileUtils::unzip($path_final_zip_file);
            unlink($path_final_zip_file);
        }

        chdir($cdir);
    }


    // Get user/team where clause
    public function getTableUserWhere(
        bool $a_team_mode = false
    ): string {
        $ilDB = $this->db;

        if ($this->getAssignment()->getAssignmentType()->isSubmissionAssignedToTeam()) {
            $team_id = $this->getTeam()->getId();
            $where = " team_id = " . $ilDB->quote($team_id, "integer") . " ";
        } else {
            if ($a_team_mode) {
                $where = " " . $ilDB->in("user_id", $this->getUserIds(), "", "integer") . " ";
            } else {
                $where = " user_id = " . $ilDB->quote($this->getUserId(), "integer");
            }
        }
        return $where;
    }


    /**
     * TODO -> get rid of getTableUserWhere and move to repository class
     * Get the date of the last submission of a user for the assignment
     */
    public function getLastSubmission(): ?string
    {
        $ilDB = $this->db;

        $ilDB->setLimit(1, 0);

        $q = "SELECT obj_id,user_id,ts FROM exc_returned" .
            " WHERE ass_id = " . $ilDB->quote($this->assignment->getId(), "integer") .
            " AND " . $this->getTableUserWhere(true) .
            " AND (filename IS NOT NULL OR atext IS NOT NULL)" .
            " AND ts IS NOT NULL" .
            " ORDER BY ts DESC";
        $usr_set = $ilDB->query($q);
        $array = $ilDB->fetchAssoc($usr_set);
        return ($array["ts"] ?? null);
    }

    /**
     * TODO -> get rid of getTableUserWhere and move to repository class
     * Get a mysql timestamp from the last HTML view opening.
     */
    public function getLastOpeningHTMLView(): ?string
    {
        $this->db->setLimit(1, 0);

        $q = "SELECT web_dir_access_time FROM exc_returned" .
            " WHERE ass_id = " . $this->db->quote($this->assignment->getId(), "integer") .
            " AND (filename IS NOT NULL OR atext IS NOT NULL)" .
            " AND web_dir_access_time IS NOT NULL" .
            " AND " . $this->getTableUserWhere(true) .
            " ORDER BY web_dir_access_time DESC";

        $res = $this->db->query($q);

        $data = $this->db->fetchAssoc($res);

        return $data["web_dir_access_time"] ?? null;
    }


    //
    // OBJECTS
    //

    /**
     * Add personal resource or repository object (ref_id) to assigment
     * @throws ilExcUnknownAssignmentTypeException
     * @throws ilExerciseException
     */
    public function addResourceObject(
        string $a_wsp_id,                   // note: text assignments currently call this with "TEXT"
        string $a_text = null
    ): int {
        $ilDB = $this->db;

        if ($this->getAssignment()->getAssignmentType()->isSubmissionAssignedToTeam()) {
            $user_id = 0;
            $team_id = $this->getTeam()->getId();
        } else {
            $user_id = $this->getUserId();
            $team_id = 0;
        }

        // repository objects must be unique in submissions
        // the same repo object cannot be used in different submissions or even different assignment/exercises
        // why? -> the access handling would fail, since the access depends e.g. on teams or even phase of the
        // assignment
        if ($this->getAssignment()->getAssignmentType()->getSubmissionType() == ilExSubmission::TYPE_REPO_OBJECT) {
            $repos_ass_type_ids = $this->ass_types->getIdsForSubmissionType(ilExSubmission::TYPE_REPO_OBJECT);
            $subs = $this->getSubmissionsForFilename($a_wsp_id, $repos_ass_type_ids);
            if ($subs !== []) {
                throw new ilExerciseException("Repository object $a_wsp_id is already assigned to another assignment.");
            }
        }

        $next_id = $ilDB->nextId("exc_returned");
        $query = sprintf(
            "INSERT INTO exc_returned " .
                         "(returned_id, obj_id, user_id, filetitle, ass_id, ts, atext, late, team_id) " .
                         "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)",
            $ilDB->quote($next_id, "integer"),
            $ilDB->quote($this->assignment->getExerciseId(), "integer"),
            $ilDB->quote($user_id, "integer"),
            $ilDB->quote($a_wsp_id, "text"),
            $ilDB->quote($this->assignment->getId(), "integer"),
            $ilDB->quote(ilUtil::now(), "timestamp"),
            $ilDB->quote($a_text, "text"),
            $ilDB->quote($this->isLate(), "integer"),
            $ilDB->quote($team_id, "integer")
        );
        $ilDB->manipulate($query);

        return $next_id;
    }

    /*
     * Remove ressource from assignement (and delete
     * its submission): Note: The object itself will not be deleted.
     */
    public function deleteResourceObject(): void
    {
        $this->deleteAllFiles();
    }

    /**
     * Handle text assignment submissions
     * @throws ilExcUnknownAssignmentTypeException
     * @throws ilExerciseException
     */
    public function updateTextSubmission(string $a_text): ?int
    {
        $ilDB = $this->db;

        $files = $this->getFiles();

        // no text = remove submission
        if (!trim($a_text)) {
            $this->deleteAllFiles();
            return null;
        }

        if (!$files) {
            return $this->addResourceObject("TEXT", $a_text);
        } else {
            $files = array_shift($files);
            $id = $files["returned_id"];
            if ($id) {
                $ilDB->manipulate("UPDATE exc_returned" .
                    " SET atext = " . $ilDB->quote($a_text, "text") .
                    ", ts = " . $ilDB->quote(ilUtil::now(), "timestamp") .
                    ", late = " . $ilDB->quote($this->isLate(), "integer") .
                    " WHERE returned_id = " . $ilDB->quote($id, "integer"));
                return $id;
            }
        }
        return null;
    }

    //
    // GUI helper
    //

    /**
     * @throws ilDateTimeException
     */
    public function getDownloadedFilesInfoForTableGUIS(): array
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        $result = array();
        $result["files"]["count"] = "---";

        // submission:
        // see if files have been resubmmited after solved
        $last_sub = $this->getLastSubmission();
        if ($last_sub) {
            $last_sub = ilDatePresentation::formatDate(new ilDateTime($last_sub, IL_CAL_DATETIME));
        } else {
            $last_sub = "---";
        }
        $result["last_submission"]["txt"] = $lng->txt("exc_last_submission");
        $result["last_submission"]["value"] = $last_sub;

        // #16994
        $ilCtrl->setParameterByClass("ilexsubmissionfilegui", "member_id", $this->getUserId());

        // assignment type specific
        switch ($this->assignment->getType()) {
            case ilExAssignment::TYPE_UPLOAD_TEAM:
                // data is merged by team - see above
                // fallthrough

            case ilExAssignment::TYPE_UPLOAD:
                $all_files = $this->getFiles();
                $late_files = 0;
                foreach ($all_files as $file) {
                    if ($file["late"]) {
                        $late_files++;
                    }
                }

                // nr of submitted files
                $result["files"]["txt"] = $lng->txt("exc_files_returned");
                if ($late_files !== 0) {
                    $result["files"]["txt"] .= ' - <span class="warning">' . $lng->txt("exc_late_submission") . " (" . $late_files . ")</span>";
                }
                $sub_cnt = count($all_files);
                $new = $this->lookupNewFiles();
                if ($new !== []) {
                    $sub_cnt .= " " . sprintf($lng->txt("cnt_new"), count($new));
                }

                $result["files"]["count"] = $sub_cnt;

                // download command
                if ($sub_cnt > 0) {
                    $result["files"]["download_url"] =
                        $ilCtrl->getLinkTargetByClass("ilexsubmissionfilegui", "downloadReturned");

                    if (count($new) <= 0) {
                        $result["files"]["download_txt"] = $lng->txt("exc_tbl_action_download_files");
                    } else {
                        $result["files"]["download_txt"] = $lng->txt("exc_tbl_action_download_all_files");
                    }

                    // download new files only
                    if ($new !== []) {
                        $result["files"]["download_new_url"] =
                            $ilCtrl->getLinkTargetByClass("ilexsubmissionfilegui", "downloadNewReturned");

                        $result["files"]["download_new_txt"] = $lng->txt("exc_tbl_action_download_new_files");
                    }
                }
                break;

            case ilExAssignment::TYPE_BLOG:
                $result["files"]["txt"] = $lng->txt("exc_blog_returned");
                $blogs = $this->getFiles();
                if ($blogs !== []) {
                    $blogs = array_pop($blogs);
                    if ($blogs && substr($blogs["filename"], -1) != "/") {
                        if ($blogs["late"]) {
                            $result["files"]["txt"] .= ' - <span class="warning">' . $lng->txt("exc_late_submission") . "</span>";
                        }

                        $result["files"]["count"] = 1;

                        $result["files"]["download_url"] =
                            $ilCtrl->getLinkTargetByClass("ilexsubmissionfilegui", "downloadReturned");

                        $result["files"]["download_txt"] = $lng->txt("exc_tbl_action_download_files");
                    }
                }
                break;

            case ilExAssignment::TYPE_PORTFOLIO:
                $result["files"]["txt"] = $lng->txt("exc_portfolio_returned");
                $portfolios = $this->getFiles();
                if ($portfolios !== []) {
                    $portfolios = array_pop($portfolios);
                    if ($portfolios && substr($portfolios["filename"], -1) != "/") {
                        if ($portfolios["late"]) {
                            $result["files"]["txt"] .= ' - <span class="warning">' . $lng->txt("exc_late_submission") . "</span>";
                        }

                        $result["files"]["count"] = 1;

                        $result["files"]["download_url"] =
                            $ilCtrl->getLinkTargetByClass("ilexsubmissionfilegui", "downloadReturned");

                        $result["files"]["download_txt"] = $lng->txt("exc_tbl_action_download_files");
                    }
                }
                break;

            case ilExAssignment::TYPE_TEXT:
                $result["files"]["txt"] = $lng->txt("exc_files_returned_text");
                $files = $this->getFiles();
                if ($files !== []) {
                    $result["files"]["count"] = 1;

                    $files = array_shift($files);
                    if (trim($files["atext"]) !== '' && trim($files["atext"]) !== '0') {
                        if ($files["late"]) {
                            $result["files"]["txt"] .= ' - <span class="warning">' . $lng->txt("exc_late_submission") . "</span>";
                        }

                        $result["files"]["download_url"] =
                            $ilCtrl->getLinkTargetByClass("ilexsubmissiontextgui", "showAssignmentText");

                        $result["files"]["download_txt"] = $lng->txt("exc_tbl_action_text_assignment_show");
                    }
                }
                break;

            case ilExAssignment::TYPE_WIKI_TEAM:
                $result["files"]["txt"] = $lng->txt("exc_wiki_returned");
                $objs = $this->getFiles();
                if ($objs !== []) {
                    $objs = array_pop($objs);
                    if ($objs && substr($objs["filename"], -1) != "/") {
                        if ($objs["late"]) {
                            $result["files"]["txt"] .= ' - <span class="warning">' . $lng->txt("exc_late_submission") . "</span>";
                        }

                        $result["files"]["count"] = 1;

                        $result["files"]["download_url"] =
                            $ilCtrl->getLinkTargetByClass("ilexsubmissionfilegui", "downloadReturned");

                        $result["files"]["download_txt"] = $lng->txt("exc_tbl_action_download_files");
                    }
                }
                break;
        }

        $ilCtrl->setParameterByClass("ilexsubmissionfilegui", "member_id", "");

        return $result;
    }

    /**
     * Get assignment return entries for a filename
     */
    public static function getSubmissionsForFilename(
        string $a_filename,
        array $a_assignment_types = array()
    ): array {
        global $DIC;

        $db = $DIC->database();

        $query = "SELECT * FROM exc_returned r LEFT JOIN exc_assignment a" .
            " ON (r.ass_id = a.id) " .
            " WHERE r.filetitle = " . $db->quote($a_filename, "string");

        if (is_array($a_assignment_types) && $a_assignment_types !== []) {
            $query .= " AND " . $db->in("a.type", $a_assignment_types, false, "integer");
        }

        $set = $db->query($query);
        $rets = array();
        while ($rec = $db->fetchAssoc($set)) {
            $rets[] = $rec;
        }


        return $rets;
    }

    public static function getDirectoryNameFromUserData(int $a_user_id): string
    {
        $userName = ilObjUser::_lookupName($a_user_id);
        return ilFileUtils::getASCIIFilename(
            trim($userName["lastname"]) . "_" .
            trim($userName["firstname"]) . "_" .
            trim($userName["login"]) . "_" .
            $userName["user_id"]
        );
    }

    public static function getAssignmentParticipants(
        int $a_exercise_id,
        int $a_ass_id
    ): array {
        global $DIC;

        $ilDB = $DIC->database();

        $participants = array();
        $query = "SELECT user_id FROM exc_returned WHERE ass_id = " .
            $ilDB->quote($a_ass_id, "integer") .
            " AND obj_id = " .
            $ilDB->quote($a_exercise_id, "integer");

        $res = $ilDB->query($query);

        while ($row = $ilDB->fetchAssoc($res)) {
            $participants[] = $row['user_id'];
        }

        return $participants;
    }
}
