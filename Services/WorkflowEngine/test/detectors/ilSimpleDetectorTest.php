<?php

/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * ilSimpleDetectorTest is part of the petri net based workflow engine.
 *
 * This class holds all tests for the class
 * detectors/class.ilSimpleDetector
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 *
 * @ingroup Services/WorkflowEngine
 */
class ilSimpleDetectorTest extends ilWorkflowEngineBaseTest
{
    private ilEmptyWorkflow $workflow;
    private ilBasicNode $node;

    protected function setUp(): void
    {
        // Empty workflow.
        require_once './Services/WorkflowEngine/classes/workflows/class.ilEmptyWorkflow.php';
        $this->workflow = new ilEmptyWorkflow();

        // Basic node
        require_once './Services/WorkflowEngine/classes/nodes/class.ilBasicNode.php';
        $this->node = new ilBasicNode($this->workflow);

        // Wiring up so the node is attached to the workflow.
        $this->workflow->addNode($this->node);

        require_once './Services/WorkflowEngine/classes/detectors/class.ilSimpleDetector.php';
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
        $detector = new ilSimpleDetector($this->node);

        // Assert
        // No exception - good
        $this->assertTrue(
            true,
            'Construction failed with valid context passed to constructor.'
        );
    }

    public function testSetDetectorState(): void
    {
        // Arrange
        $workflow = new ilEmptyWorkflow();
        $node = new ilBasicNode($workflow);
        $detector = new ilSimpleDetector($node);
        $workflow->addNode($node);


        // Act
        $detector->setDetectorState(true);


        // Assert
        $valid_state = true;

        if (!$detector->getDetectorState()) {
            $valid_state = false;
        }

        if ($node->isActive()) {
            // With this single detector satisfied, the
            // parent node should have transitted and
            // this would result in it being inactive
            // afterwards.
            $valid_state = false;
        }

        $this->assertTrue($valid_state, 'Invalid state after setting of detector state.');
    }

    public function testTrigger(): void
    {
        // Arrange
        $workflow = new ilEmptyWorkflow();
        $node = new ilBasicNode($workflow);
        $detector = new ilSimpleDetector($node);
        $workflow->addNode($node);


        // Act
        $detector->trigger(null);

        // Assert
        $valid_state = true;

        if (!$detector->getDetectorState()) {
            $valid_state = false;
        }

        if ($node->isActive()) {
            // With this single detector satisfied, the
            // parent node should have transitted and
            // this would result in it being inactive
            // afterwards.
            $valid_state = false;
        }

        $this->assertTrue($valid_state, 'Invalid state after setting of detector state.');
    }

    public function testGetContext(): void
    {
        // Arrange
        $detector = new ilSimpleDetector($this->node);

        // Act
        $actual = $detector->getContext();

        // Assert
        if ($actual === $this->node) {
            $this->assertEquals($actual, $this->node);
        } else {
            $this->fail('Context not identical.');
        }
    }
}
