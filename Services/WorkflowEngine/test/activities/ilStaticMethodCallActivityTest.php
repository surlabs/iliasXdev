<?php

/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * ilStaticMethodCallActivityTest is part of the petri net based workflow engine.
 *
 * This class holds all tests for the class
 * activities/class.ilStaticMethodCallActivity
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 *
 * @ingroup Services/WorkflowEngine
 */
class ilStaticMethodCallActivityTest extends ilWorkflowEngineBaseTest
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

        require_once './Services/WorkflowEngine/classes/activities/class.ilStaticMethodCallActivity.php';
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
        $activity = new ilStaticMethodCallActivity($this->node);

        // Assert
        // No exception - good
        $this->assertTrue(
            true,
            'Construction failed with valid context passed to constructor.'
        );
    }

    public function testSetGetIncludeFilename(): void
    {
        // Arrange
        $activity = new ilStaticMethodCallActivity($this->node);
        $expected = 'Services/WorkflowEngine/classes/utils/class.ilWorkflowUtils.php';

        // Act
        $activity->setIncludeFilename($expected);
        $actual = $activity->getIncludeFilename();

        // Assert
        $this->assertEquals($actual, $expected);
    }

    public function testSetGetClassAndMethodName(): void
    {
        // Arrange
        $activity = new ilStaticMethodCallActivity($this->node);
        $expected = 'ilWorkflowUtils::targetMethod';

        // Act
        $activity->setClassAndMethodName($expected);
        $actual = $activity->getClassAndMethodName();

        // Assert
        $this->assertEquals($actual, $expected);
    }

    public function testSetGetParameters(): void
    {
        // Arrange
        $activity = new ilStaticMethodCallActivity($this->node);
        $expected = array('homer', 'marge', 'bart', 'lisa', 'maggy');

        // Act
        $activity->setParameters($expected);
        $actual = $activity->getParameters();

        // Assert
        $this->assertEquals($actual, $expected);
    }

    public function testExecute(): void
    {
        // Arrange
        $activity = new ilStaticMethodCallActivity($this->node);
        $file = 'Services/WorkflowEngine/test/activities/ilStaticMethodCallActivityTest.php';
        $class_and_method = 'ilStaticMethodCallActivityTest::executionTargetMethod';
        $parameters = array('homer', 'marge', 'bart', 'lisa', 'maggy');

        // Act
        $activity->setIncludeFilename($file);
        $activity->setClassAndMethodName($class_and_method);
        $activity->setParameters($parameters);
        $activity->execute();

        // Assert
        $this->assertTrue(true, 'There dont seem to be problems here.');
    }

    public static function executionTargetMethod($context, $param): bool
    {
        $parameters = array(
          'homer' => 'homer', 0 => 'homer',
          'marge' => 'marge', 1 => 'marge',
          'bart' => 'bart', 2 => 'bart',
          'lisa' => 'lisa', 3 => 'lisa',
          'maggy' => 'maggy', 4 => 'maggy'
        );

        if ($context == null) {
            throw new Exception('Something went wrong with the context.');
        }

        if ($param[0] != $parameters) {
            throw new Exception('Something went wrong with the parameters.');
        }

        return true;
    }

    public function testGetContext(): void
    {
        // Arrange
        $activity = new ilStaticMethodCallActivity($this->node);

        // Act
        $actual = $activity->getContext();

        // Assert
        if ($actual === $this->node) {
            $this->assertEquals($actual, $this->node);
        } else {
            $this->fail('Context not identical.');
        }
    }
}
