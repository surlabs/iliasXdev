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

use ILIAS\DI\Container;
use ILIAS\DI\UIServices;
use ILIAS\Refinery\Factory as RefineryFactory;
use ILIAS\Test\InternalRequestService;
use Psr\Http\Message\RequestInterface;

/**
 * Class ilTestCorrectionsGUI
 *
 * @author    Björn Heyser <info@bjoernheyser.de>
 * @version    $Id$
 *
 * @package    Modules/Test
 */
class ilTestCorrectionsGUI
{
    private InternalRequestService $testrequest;

    protected ilDBInterface $database;
    protected ilCtrl $ctrl;
    protected ilLanguage $language;
    protected ilTabsGUI $tabs;
    protected ilHelpGUI $help;
    protected UIServices $ui;
    protected RefineryFactory $refinery;
    protected RequestInterface $request;
    protected ilObjTest $testOBJ;
    protected ilTestAccess $testAccess;

    /**
     * ilTestCorrectionsGUI constructor.
     * @param \ILIAS\DI\Container $DIC
     * @param ilObjTest $testOBJ
     */
    public function __construct(Container $DIC, ilObjTest $testOBJ)
    {
        $this->database = $DIC->database();
        $this->ctrl = $DIC->ctrl();
        $this->language = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->help = $DIC->help();
        $this->ui = $DIC->ui();
        $this->refinery = $DIC->refinery();
        $this->request = $DIC->http()->request();
        $this->testOBJ = $testOBJ;
        $this->testrequest = $DIC->test()->internal()->request();
        $this->testAccess = new ilTestAccess($testOBJ->getRefId(), $testOBJ->getTestId());
    }

    public function executeCommand()
    {
        if (!$this->testAccess->checkCorrectionsAccess()) {
            ilObjTestGUI::accessViolationRedirect();
        }
        if (
            $this->testrequest->isset('eqid') && (int) $this->testrequest->raw('eqid')
            && $this->testrequest->isset('eqpl') && (int) $this->testrequest->raw('eqpl')
        ) {
            $this->ctrl->setParameter($this, 'qid', (int) $this->testrequest->raw('eqid'));
            $this->ctrl->redirect($this, 'showQuestion');
        }
        if ($this->testrequest->isset('removeQid') && (int) $this->testrequest->raw('removeQid')) {
            $this->ctrl->setParameter($this, 'qid', (int) $this->testrequest->raw('removeQid'));
            $this->ctrl->redirect($this, 'confirmQuestionRemoval');
        }

        if ((int) $this->testrequest->raw('qid')
            && !$this->checkQuestion((int) $this->testrequest->raw('qid'))) {
            ilObjTestGUI::accessViolationRedirect();
        }

        $this->ctrl->saveParameter($this, 'qid');

        switch ($this->ctrl->getNextClass($this)) {
            default:

                $command = $this->ctrl->getCmd('showQuestionList');
                $this->{$command}();
        }
    }

    protected function showQuestionList()
    {
        $this->tabs->activateTab(ilTestTabsManager::TAB_ID_CORRECTION);

        $ui = $this->ui;

        if ($this->testOBJ->isFixedTest()) {
            $table_gui = new ilTestQuestionsTableGUI(
                $this,
                'showQuestionList',
                $this->testOBJ->getRefId()
            );

            $table_gui->setQuestionRemoveRowButtonEnabled(true);
            $table_gui->init();

            $table_gui->setData($this->getQuestions());

            $rendered_gui_component = $table_gui->getHTML();
        } else {
            $lng = $this->language;
            $txt = $lng->txt('tst_corrections_incompatible_question_set_type');

            $infoBox = $ui->factory()->messageBox()->info($txt);

            $rendered_gui_component = $ui->renderer()->render($infoBox);
        }

        $ui->mainTemplate()->setContent($rendered_gui_component);
    }

    protected function showQuestion(ilPropertyFormGUI $form = null)
    {
        $questionGUI = $this->getQuestion((int) $this->testrequest->raw('qid'));

        $this->setCorrectionTabsContext($questionGUI, 'question');

        if ($form === null) {
            $form = $this->buildQuestionCorrectionForm($questionGUI);
        }

        $this->populatePageTitleAndDescription($questionGUI);
        $this->ui->mainTemplate()->setContent($form->getHTML());
    }

    protected function saveQuestion()
    {
        $questionGUI = $this->getQuestion((int) $this->testrequest->raw('qid'));

        $form = $this->buildQuestionCorrectionForm($questionGUI);

        $form->setValuesByPost();

        if (!$form->checkInput()) {
            $questionGUI->prepareReprintableCorrectionsForm($form);

            $this->showQuestion($form);
            return;
        }

        $questionGUI->saveCorrectionsFormProperties($form);
        $questionGUI->object->setPoints($questionGUI->object->getMaximumPoints());
        $questionGUI->object->saveToDb();

        $scoring = new ilTestScoring($this->testOBJ);
        $scoring->setPreserveManualScores(false);
        $scoring->setQuestionId($questionGUI->object->getId());
        $scoring->recalculateSolutions();

        $this->ui->mainTemplate()->setOnScreenMessage('success', $this->language->txt('saved_successfully'), true);
        $this->ctrl->redirect($this, 'showQuestion');
    }

    protected function buildQuestionCorrectionForm(assQuestionGUI $questionGUI): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setId('tst_question_correction');

        $form->setTitle($this->language->txt('tst_corrections_qst_form'));

        $hiddenQid = new ilHiddenInputGUI('qid');
        $hiddenQid->setValue($questionGUI->object->getId());
        $form->addItem($hiddenQid);

        $questionGUI->populateCorrectionsFormProperties($form);

        $scoring = new ilTestScoring($this->testOBJ);
        $scoring->setQuestionId($questionGUI->object->getId());

        if ($scoring->getNumManualScorings()) {
            $form->addCommandButton('confirmManualScoringReset', $this->language->txt('save'));
        } else {
            $form->addCommandButton('saveQuestion', $this->language->txt('save'));
        }

        return $form;
    }

    protected function addHiddenItemsFromArray(ilConfirmationGUI $gui, $array, $curPath = array())
    {
        foreach ($array as $name => $value) {
            if ($name == 'cmd' && !count($curPath)) {
                continue;
            }

            if (count($curPath)) {
                $name = "[{$name}]";
            }

            if (is_array($value)) {
                $nextPath = array_merge($curPath, array($name));
                $this->addHiddenItemsFromArray($gui, $value, $nextPath);
            } else {
                $postVar = implode('', $curPath) . $name;
                $gui->addHiddenItem($postVar, $value);
            }
        }
    }

    protected function confirmManualScoringReset()
    {
        $questionGUI = $this->getQuestion((int) $this->testrequest->raw('qid'));

        $this->setCorrectionTabsContext($questionGUI, 'question');

        $scoring = new ilTestScoring($this->testOBJ);
        $scoring->setQuestionId($questionGUI->object->getId());

        $confirmation = sprintf(
            $this->language->txt('tst_corrections_manscore_reset_warning'),
            $scoring->getNumManualScorings(),
            $questionGUI->object->getTitle(),
            $questionGUI->object->getId()
        );

        $gui = new ilConfirmationGUI();
        $gui->setHeaderText($confirmation);
        $gui->setFormAction($this->ctrl->getFormAction($this));
        $gui->setCancel($this->language->txt('cancel'), 'showQuestion');
        $gui->setConfirm($this->language->txt('confirm'), 'saveQuestion');

        $this->addHiddenItemsFromArray($gui, $this->testrequest->getParsedBody());

        $this->ui->mainTemplate()->setContent($gui->getHTML());
    }

    protected function showSolution()
    {
        $questionGUI = $this->getQuestion((int) $this->testrequest->raw('qid'));

        $this->setCorrectionTabsContext($questionGUI, 'solution');

        $pageGUI = new ilAssQuestionPageGUI($questionGUI->object->getId());
        $pageGUI->setRenderPageContainer(false);
        $pageGUI->setEditPreview(true);
        $pageGUI->setEnabledTabs(false);

        $solutionHTML = $questionGUI->getSolutionOutput(
            0,
            null,
            false,
            false,
            true,
            false,
            true,
            false,
            true
        );

        $pageGUI->setQuestionHTML(array($questionGUI->object->getId() => $solutionHTML));
        $pageGUI->setPresentationTitle($questionGUI->object->getTitle());

        $tpl = new ilTemplate('tpl.tst_corrections_solution_presentation.html', true, true, 'Modules/Test');
        $tpl->setVariable('SOLUTION_PRESENTATION', $pageGUI->preview());

        $this->populatePageTitleAndDescription($questionGUI);

        $this->ui->mainTemplate()->setContent($tpl->get());

        $this->ui->mainTemplate()->setCurrentBlock("ContentStyle");
        $stylesheet = ilObjStyleSheet::getContentStylePath(0);
        $this->ui->mainTemplate()->setVariable("LOCATION_CONTENT_STYLESHEET", $stylesheet);
        $this->ui->mainTemplate()->parseCurrentBlock();

        $this->ui->mainTemplate()->setCurrentBlock("SyntaxStyle");
        $stylesheet = ilObjStyleSheet::getSyntaxStylePath();
        $this->ui->mainTemplate()->setVariable("LOCATION_SYNTAX_STYLESHEET", $stylesheet);
        $this->ui->mainTemplate()->parseCurrentBlock();
    }

    protected function showAnswerStatistic()
    {
        $questionGUI = $this->getQuestion((int) $this->testrequest->raw('qid'));
        $solutions = $this->getSolutions($questionGUI->object);

        $this->setCorrectionTabsContext($questionGUI, 'answers');

        $tablesHtml = '';

        foreach ($questionGUI->getSubQuestionsIndex() as $subQuestionIndex) {
            $table = $questionGUI->getAnswerFrequencyTableGUI(
                $this,
                'showAnswerStatistic',
                $solutions,
                $subQuestionIndex
            );

            $tablesHtml .= $table->getHTML() . $table->getAdditionalHtml();
        }

        $this->populatePageTitleAndDescription($questionGUI);
        $this->ui->mainTemplate()->setContent($tablesHtml);
    }

    protected function addAnswer()
    {
        $form_builder = new ilAddAnswerFormBuilder($this, $this->ui->factory(), $this->refinery, $this->language, $this->ctrl);

        $form = $form_builder->buildAddAnswerForm()
            ->withRequest($this->request);

        $data = $form->getData();
        $question_id = $data['question_id'];

        if (!$this->checkQuestion($question_id)) {
            $this->ui->mainTemplate()->setOnScreenMessage('failure', $this->language->txt('form_input_not_valid'));
            $this->showAnswerStatistic();
            return;
        }

        $question_gui = $this->getQuestion($question_id);

        $question_index = $data['question_index'];
        $answer_value = $data['answer_value'];
        $points = $data['points'];

        if (!$points) {
            $this->ui->mainTemplate()->setOnScreenMessage('failure', $this->language->txt('err_no_numeric_value'));
            $this->showAnswerStatistic();
            return;
        }

        if ($question_gui->object->isAddableAnswerOptionValue($question_index, $answer_value)) {
            $question_gui->object->addAnswerOptionValue($question_index, $answer_value, $points);
            $question_gui->object->saveToDb();
        }

        $scoring = new ilTestScoring($this->testOBJ);
        $scoring->setPreserveManualScores(true);
        $scoring->recalculateSolutions();

        $this->ui->mainTemplate()->setOnScreenMessage('success', $this->language->txt('saved_successfully'));
        $this->showAnswerStatistic();
    }

    protected function confirmQuestionRemoval()
    {
        $this->tabs->activateTab(ilTestTabsManager::TAB_ID_CORRECTION);

        $questionGUI = $this->getQuestion((int) $this->testrequest->raw('qid'));

        $confirmation = sprintf(
            $this->language->txt('tst_corrections_qst_remove_confirmation'),
            $questionGUI->object->getTitle(),
            $questionGUI->object->getId()
        );

        $buttons = array(
            $this->ui->factory()->button()->standard(
                $this->language->txt('confirm'),
                $this->ctrl->getLinkTarget($this, 'performQuestionRemoval')
            ),
            $this->ui->factory()->button()->standard(
                $this->language->txt('cancel'),
                $this->ctrl->getLinkTarget($this, 'showQuestionList')
            )
        );

        $this->ui->mainTemplate()->setContent($this->ui->renderer()->render(
            $this->ui->factory()->messageBox()->confirmation($confirmation)->withButtons($buttons)
        ));
    }

    protected function performQuestionRemoval(): void
    {
        $questionGUI = $this->getQuestion((int) $this->testrequest->raw('qid'));
        $scoring = new ilTestScoring($this->testOBJ);

        $participantData = new ilTestParticipantData($this->database, $language);
        $participantData->load($this->testOBJ->getTestId());

        // remove question solutions
        $questionGUI->object->removeAllExistingSolutions();

        // remove test question results
        $scoring->removeAllQuestionResults($questionGUI->object->getId());

        // remove question from test and reindex remaining questions
        $this->testOBJ->removeQuestion($questionGUI->object->getId());
        $reindexedSequencePositionMap = $this->testOBJ->reindexFixedQuestionOrdering();
        $this->testOBJ->loadQuestions();

        // remove questions from all sequences
        $this->testOBJ->removeQuestionFromSequences(
            $questionGUI->object->getId(),
            $participantData->getActiveIds(),
            $reindexedSequencePositionMap
        );

        // update pass and test results
        $scoring->updatePassAndTestResults($participantData->getActiveIds());

        // trigger learning progress
        ilLPStatusWrapper::_refreshStatus($this->testOBJ->getId(), $participantData->getUserIds());

        // finally delete the question itself
        $questionGUI->object->delete($questionGUI->object->getId());

        // check for empty test and set test offline
        if (!count($this->testOBJ->getTestQuestions())) {
            $this->testOBJ->setOnline(false);
            $this->testOBJ->saveToDb(true);
        }

        $this->ctrl->setParameter($this, 'qid', '');
        $this->ctrl->redirect($this, 'showQuestionList');
    }

    protected function setCorrectionTabsContext(assQuestionGUI $questionGUI, $activeTabId)
    {
        $this->tabs->clearTargets();
        $this->tabs->clearSubTabs();

        $this->help->setScreenIdComponent("tst");
        $this->help->setScreenId("scoringadjust");
        $this->help->setSubScreenId($activeTabId);


        $this->tabs->setBackTarget(
            $this->language->txt('back'),
            $this->ctrl->getLinkTarget($this, 'showQuestionList')
        );

        $this->tabs->addTab(
            'question',
            $this->language->txt('tst_corrections_tab_question'),
            $this->ctrl->getLinkTarget($this, 'showQuestion')
        );

        $this->tabs->addTab(
            'solution',
            $this->language->txt('tst_corrections_tab_solution'),
            $this->ctrl->getLinkTarget($this, 'showSolution')
        );

        if ($questionGUI->isAnswerFrequencyStatisticSupported()) {
            $this->tabs->addTab(
                'answers',
                $this->language->txt('tst_corrections_tab_statistics'),
                $this->ctrl->getLinkTarget($this, 'showAnswerStatistic')
            );
        }

        $this->tabs->activateTab($activeTabId);
    }

    /**
     * @param assQuestionGUI $questionGUI
     */
    protected function populatePageTitleAndDescription(assQuestionGUI $questionGUI)
    {
        $this->ui->mainTemplate()->setTitle($questionGUI->object->getTitle());
        $this->ui->mainTemplate()->setDescription($questionGUI->outQuestionType());
    }

    /**
     * @param int $qId
     * @return bool
     */
    protected function checkQuestion($qId): bool
    {
        if (!$this->testOBJ->isTestQuestion($qId)) {
            return false;
        }

        $questionGUI = $this->getQuestion($qId);

        if (!$this->supportsAdjustment($questionGUI)) {
            return false;
        }

        if (!$this->allowedInAdjustment($questionGUI)) {
            return false;
        }

        return true;
    }

    /**
     * @param int $qId
     * @return assQuestionGUI
     */
    protected function getQuestion($qId): assQuestionGUI
    {
        $question = assQuestion::instantiateQuestionGUI($qId);
        $question->object->setObjId($this->testOBJ->getId());

        return $question;
    }

    protected function getSolutions(assQuestion $question): array
    {
        $solutionRows = array();

        foreach ($this->testOBJ->getParticipants() as $activeId => $participantData) {
            $passesSelector = new ilTestPassesSelector($this->database, $this->testOBJ);
            $passesSelector->setActiveId($activeId);
            $passesSelector->loadLastFinishedPass();

            foreach ($passesSelector->getClosedPasses() as $pass) {
                foreach ($question->getSolutionValues($activeId, $pass) as $row) {
                    $solutionRows[] = $row;
                }
            }
        }

        return $solutionRows;
    }

    /**
     * @return array
     */
    protected function getQuestions(): array
    {
        $questions = array();

        foreach ($this->testOBJ->getTestQuestions() as $questionData) {
            $questionGUI = $this->getQuestion($questionData['question_id']);

            if (!$this->supportsAdjustment($questionGUI)) {
                continue;
            }

            if (!$this->allowedInAdjustment($questionGUI)) {
                continue;
            }

            $questions[] = $questionData;
        }

        return $questions;
    }

    /**
     * Returns if the given question object support scoring adjustment.
     *
     * @param $question_object assQuestionGUI
     *
     * @return bool True, if relevant interfaces are implemented to support scoring adjustment.
     */
    protected function supportsAdjustment(\assQuestionGUI $question_object): bool
    {
        return ($question_object instanceof ilGuiQuestionScoringAdjustable
                || $question_object instanceof ilGuiAnswerScoringAdjustable)
            && ($question_object->object instanceof ilObjQuestionScoringAdjustable
                || $question_object->object instanceof ilObjAnswerScoringAdjustable);
    }

    /**
     * Returns if the question type is allowed for adjustments in the global test administration.
     *
     * @param assQuestionGUI $question_object
     * @return bool
     */
    protected function allowedInAdjustment(\assQuestionGUI $question_object): bool
    {
        $setting = new ilSetting('assessment');
        $types = explode(',', $setting->get('assessment_scoring_adjustment'));
        require_once './Modules/TestQuestionPool/classes/class.ilObjQuestionPool.php';
        $type_def = array();
        foreach ($types as $type) {
            $type_def[$type] = ilObjQuestionPool::getQuestionTypeByTypeId($type);
        }

        $type = $question_object->getQuestionType();
        if (in_array($type, $type_def)) {
            return true;
        }
        return false;
    }
}
