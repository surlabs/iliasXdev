<?php

/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */

use org\bovigo\vfs;

/**
 * ilBasicNodeTest is part of the petri net based workflow engine.
 *
 * This class holds all tests for the class
 * nodes/class.ilBasicNode
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 *
 * @ingroup Services/WorkflowEngine
 */
class ilBasicNodeTest extends ilWorkflowEngineBaseTest
{
    private ilEmptyWorkflow $workflow;

    protected function setUp(): void
    {
        // Empty workflow.
        require_once './Services/WorkflowEngine/classes/workflows/class.ilEmptyWorkflow.php';
        $this->workflow = new ilEmptyWorkflow();
    }

    protected function tearDown(): void
    {
        global $DIC;

        if (isset($DIC['ilSetting'])) {
            $DIC['ilSetting']->delete('IL_PHPUNIT_TEST_TIME');
            $DIC['ilSetting']->delete('IL_PHPUNIT_TEST_MICROTIME');
        }
    }

    public function testConstructorValidContext(): void
    {
        // Act
        $node = new ilBasicNode($this->workflow);

        // Assert
        // No exception - good
        $this->assertTrue(
            true,
            'Construction failed with valid context passed to constructor.'
        );
    }

    public function testGetContext(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);

        // Act
        $actual = $node->getContext();

        // Assert
        if ($actual === $this->workflow) {
            $this->assertEquals($actual, $this->workflow);
        } else {
            $this->fail('Context not identical.');
        }
    }

    public function testIsActiveAndActivate(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        // Act
        $node->activate();

        // Assert
        $actual = $node->isActive();
        $this->assertTrue($actual);
    }

    public function testDeactivate(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        // Act
        $node->activate();
        $was_activated = $node->isActive();
        $node->deactivate();
        $was_deactivated = !$node->isActive();

        // Assert
        $this->assertEquals($was_activated, $was_deactivated);
    }

    public function testCheckTransitionPreconditionsValid(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        // Act
        $detector->trigger(array());
        $preconditions = $node->checkTransitionPreconditions();

        // Assert
        $this->assertTrue($preconditions);
    }

    public function testCheckTransitionPreconditionsInvalid(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);


        // Act
        $preconditions = $node->checkTransitionPreconditions();

        // Assert
        $this->assertFalse($preconditions);
    }

    public function testAttemptTransitionValid(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        // Act
        $detector->trigger(array());
        $result = $node->attemptTransition();

        // Assert
        $this->assertTrue($result);
    }

    public function testAttemptTransitionInvalid(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        // Act
        $result = $node->attemptTransition();

        // Assert
        $this->assertFalse($result);
    }

    public function testExecuteTransition(): void
    { // This is test #100 of the WorkflowEngine, written on 9th of May, 2012
        // @ 14:15

        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);
        $node->activate();

        // Act
        $node->executeTransition();
        $state = $node->isActive();

        // Assert
        $this->assertFalse($state);
    }

    public function testExecuteActivitiesViaExecuteTransition(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        vfs\vfsStream::setup('example');

        require_once './Services/WorkflowEngine/classes/activities/class.ilLoggingActivity.php';
        $activity = new ilLoggingActivity($node);
        $activity->setLogFile(vfs\vfsStream::url('example/ilTransitionLog.txt'));
        $activity->setLogLevel('MESSAGE');
        $activity->setLogMessage('TEST');
        $node->addActivity($activity);

        // Act
        $node->activate();
        $node->executeTransition();

        // Assert
        $expected = ' :: MESSAGE :: TEST';
        $fp = fopen(vfs\vfsStream::url('example/ilTransitionLog.txt'), 'rb');
        $line = fgets($fp);
        $actual = substr($line, 25, -2);
        $this->assertEquals(
            $actual,
            $expected
        );
    }

    public function testExecuteEmitterViaExecuteTransition(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        require_once './Services/WorkflowEngine/classes/emitters/class.ilActivationEmitter.php';
        $t_node = new ilBasicNode($this->workflow);
        $t_detector = new ilSimpleDetector($t_node);
        $t_node->addDetector($t_detector);
        $foo_detector = new ilSimpleDetector($t_node);
        $t_node->addDetector($foo_detector);
        // again a foo_detector to keep the t_node from transitting

        $emitter = new ilActivationEmitter($node);
        $emitter->setTargetDetector($t_detector);
        $node->addEmitter($emitter);

        // Act
        $node->activate();
        $node->executeTransition();

        // Assert
        $this->assertTrue($t_node->isActive());
    }

    public function testAddDetector(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);

        // Act
        $node->addDetector($detector);
        $detectors = $node->getDetectors();

        // Assert
        $this->assertEquals($detector, $detectors[0]);
    }

    public function testAddEmitter(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/emitters/class.ilSimpleEmitter.php';
        $emitter = new ilSimpleEmitter($node);

        // Act
        $node->addEmitter($emitter);
        $emitters = $node->getEmitters();

        // Assert
        $this->assertEquals($emitter, $emitters[0]);
    }

    public function testAddActivity(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/activities/class.ilLoggingActivity.php';
        $activity = new ilLoggingActivity($node);

        // Act
        $node->addActivity($activity);
        $activities = $node->getActivities();

        // Assert
        $this->assertEquals($activity, $activities[0]);
    }

    public function testNotifyDetectorSatisfaction(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);

        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        // Note: Order is important. Setting detector state prior to activation
        // will be voided.
        $node->activate();
        $detector->setDetectorState(true);
        /* Setting the detector to true will actually be reported
         * with notifyDetectorSatisfaction.
         * To isolate this call, we need to reset the node back
         * to active prior to evaluating if it successfully executes
         * the transition and sets itself to inactive.
         */

        // Act
        $node->notifyDetectorSatisfaction($detector);

        // Assert
        $this->assertFalse($node->isActive());
    }

    public function testSetGetIsForwardConditionNode(): void
    {
        // Arrange
        $node = new ilBasicNode($this->workflow);
        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
        $detector = new ilSimpleDetector($node);
        $node->addDetector($detector);

        // Act
        $node->activate();

        // Assert
        $this->assertFalse($node->isForwardConditionNode(), 'Forward condition should be false by default.');
        $node->setIsForwardConditionNode(true);
        $this->assertTrue($node->isForwardConditionNode(), 'Forward condition node state not properly stored.');
    }
}
