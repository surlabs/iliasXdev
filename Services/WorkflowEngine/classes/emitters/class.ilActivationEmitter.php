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
 * ilActivationEmitter is part of the petri net based workflow engine.
 *
 * The activation emitter is an internal signals yeoman, doing nothing but activating
 * the designated target node. While the simple emitter triggers an open detector,
 * this emitter is used to activate inactive nodes.
 * In a regular petri net, all nodes are active. The modelling of nodes, which may
 * only transit when preconditions are met, offer a decent amount of planning.
 * To offer a shortcut and to optimize load for the workflowcontroller, nodes
 * default to being inactive. They, as well as their detectors, feature methods
 * that are fired during activation/deactivation. The activation emitter sits
 * in the slot of an emitter, due to the small interface involved a comfortable
 * place, and signals not only to a node, but activates the target node before.
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @ingroup Services/WorkflowEngine
 */
class ilActivationEmitter implements ilEmitter, ilWorkflowEngineElement
{
    /**
     * This holds a reference to the detector, which is to be triggered.
     */
    private ilDetector $target_detector;

    /**
     * This holds a reference to the parent ilNode.
     */
    private ilNode $context;

    /** Holds information if the emitter emitted at least once. */
    private bool $emitted;

    protected string $name;

    /**
     * Default constructor.
     *
     * @param ilNode $context Reference to the parent node.
     */
    public function __construct(ilNode $context)
    {
        $this->context = $context;
        $this->emitted = false;
    }

    /**
     * Sets the target detector for this emitter.
     *
     * @param ilDetector $a_target_detector
     */
    public function setTargetDetector(ilDetector $a_target_detector): void
    {
        $this->target_detector = $a_target_detector;
    }

    /**
     * Gets the currently set target detector of this emitter.
     *
     * @return ilDetector Reference to the target detector.
     */
    public function getTargetDetector(): ilDetector
    {
        return $this->target_detector;
    }

    /**
     * Returns a reference to the parent node of this emitter.
     *
     * @return ilNode Reference to the parent node.
     */
    public function getContext(): ilNode
    {
        return $this->context;
    }

    /**
     * Executes this emitter after activating the target node.
     */
    public function emit(): void
    {
        $this->emitted = true;
        $target_node = $this->target_detector->getContext();
        $target_node->activate();
        $this->target_detector->trigger([]);
    }

    public function getActivated(): bool
    {
        return $this->emitted;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
