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
 ********************************************************************
 */

/**
 * @author Oskar Truffer <ot@studer-raimann.ch>
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class ilDclNReferenceRecordFieldModel extends ilDclReferenceRecordFieldModel
{
    protected int $max_reference_length = 20;

    public function getMaxReferenceLength(): int
    {
        return $this->max_reference_length;
    }

    public function setMaxReferenceLength(int $max_reference_length): void
    {
        $this->max_reference_length = $max_reference_length;
    }

    public function doUpdate(): void
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $values = $this->getValue();
        if (!is_array($values)) {
            $values = array($values);
        }
        $datatype = $this->getField()->getDatatype();

        $query = "DELETE FROM il_dcl_stloc" . $datatype->getStorageLocation() . "_value WHERE record_field_id = "
            . $ilDB->quote($this->id, "integer");
        $ilDB->manipulate($query);

        if (!count($values) || $values[0] == 0) {
            return;
        }

        $query = "INSERT INTO il_dcl_stloc" . $datatype->getStorageLocation() . "_value (value, record_field_id, id) VALUES";
        foreach ($values as $value) {
            $next_id = $ilDB->nextId("il_dcl_stloc" . $datatype->getStorageLocation() . "_value");
            $query .= " (" . $ilDB->quote($value, $datatype->getDbType()) . ", " . $ilDB->quote(
                $this->getId(),
                "integer"
            ) . ", "
                . $ilDB->quote($next_id, "integer") . "),";
        }
        $query = substr($query, 0, -1);
        $ilDB->manipulate($query);
    }

    public function getValue(): array
    {
        $this->loadValue();
        return $this->value;
    }

    protected function loadValueSorted(): void
    {
        if ($this->value === null) {
            global $DIC;
            $ilDB = $DIC['ilDB'];
            $datatype = $this->getField()->getDatatype();
            $refField = ilDclCache::getFieldCache($this->getField()->getFieldRef());

            $supported_internal_types = array(
                ilDclDatatype::INPUTFORMAT_ILIAS_REF,
                ilDclDatatype::INPUTFORMAT_MOB,
                ilDclDatatype::INPUTFORMAT_FILE,
            );

            $supported_types = array_merge(
                array(
                    ilDclDatatype::INPUTFORMAT_TEXT,
                    ilDclDatatype::INPUTFORMAT_NUMBER,
                    ilDclDatatype::INPUTFORMAT_BOOLEAN,
                ),
                $supported_internal_types
            );
            $datatypeId = $refField->getDatatypeId();
            if (in_array($datatypeId, $supported_types)) {
                if (in_array($datatypeId, $supported_internal_types)) {
                    $query = "SELECT stlocOrig.value AS value,  ilias_object.title AS value_ref ";
                } else {
                    $query = "SELECT stlocOrig.value AS value,  stlocRef.value AS value_ref ";
                }
                $query .= "FROM il_dcl_stloc" . $datatype->getStorageLocation() . "_value AS stlocOrig  ";

                $query .= " INNER JOIN il_dcl_record_field AS refField ON stlocOrig.value = refField.record_id AND refField.field_id = "
                    . $ilDB->quote($refField->getId(), "integer");
                $query .= " INNER JOIN il_dcl_stloc" . $refField->getStorageLocation()
                    . "_value AS stlocRef ON stlocRef.record_field_id = refField.id ";
            } else {
                $query = "SELECT stlocOrig.value AS value ";
                $query .= "FROM il_dcl_stloc" . $datatype->getStorageLocation() . "_value AS stlocOrig  ";
            }

            switch ($datatypeId) {
                case ilDclDatatype::INPUTFORMAT_ILIAS_REF:
                    $query .= " INNER JOIN object_reference AS ilias_ref ON ilias_ref.ref_id = stlocRef.value ";
                    $query .= " INNER JOIN object_data AS ilias_object ON ilias_object.obj_id = ilias_ref.obj_id ";
                    break;
                case ilDclDatatype::INPUTFORMAT_MOB:
                case ilDclDatatype::INPUTFORMAT_FILE:
                    $query .= " INNER JOIN object_data AS ilias_object ON ilias_object.obj_id =  stlocRef.value ";
                    break;
            }
            $query .= " WHERE stlocOrig.record_field_id = " . $ilDB->quote($this->id, "integer");
            if (in_array($datatypeId, $supported_types)) {
                $query .= " ORDER BY value_ref ASC";
            }

            $set = $ilDB->query($query);

            $this->value = array();
            while ($rec = $ilDB->fetchAssoc($set)) {
                $this->value[] = $rec['value'];
            }
        }
    }

    protected function loadValue(): void
    {
        if ($this->value === null) {
            global $DIC;
            $ilDB = $DIC['ilDB'];
            $datatype = $this->getField()->getDatatype();
            $query = "SELECT * FROM il_dcl_stloc" . $datatype->getStorageLocation() . "_value WHERE record_field_id = "
                . $ilDB->quote($this->id, "integer");
            $set = $ilDB->query($query);
            $this->value = array();
            while ($rec = $ilDB->fetchAssoc($set)) {
                $this->value[] = $rec['value'];
            }
        }
    }

    /**
     * @description this funciton is used to in the viewdefinition of a single record.
     */
    public function getSingleHTML($options = null): string
    {
        $ilDataCollectionNReferenceFieldGUI = new ilDclNReferenceFieldGUI($this);

        return $ilDataCollectionNReferenceFieldGUI->getSingleHTML($options);
    }

    public function getLinkHTML(string $link, int $value): string
    {
        if ($link === "[" . $this->getField()->getTitle() . "]") {
            //$link = null;
            return "";
        }

        return "<a href=\"$link\">$value</a>";
    }

    public function getHTML(): string
    {
        $ilDataCollectionNReferenceFieldGUI = new ilDclNReferenceFieldGUI($this);

        return $ilDataCollectionNReferenceFieldGUI->getHTML();
    }

    public function getValueFromExcel(ilExcel $excel, int $row, int $col): array
    {
        global $DIC;
        $lng = $DIC['lng'];
        $stringValue = parent::getValueFromExcel($excel, $row, $col);
        $this->getReferencesFromString($stringValue);
        $referenceIds = $this->getReferencesFromString($stringValue);
        if (!count($referenceIds) && $stringValue) {
            $warning = "(" . $row . ", " . ilDataCollectionImporter::getExcelCharForInteger($col + 1) . ") " . $lng->txt("dcl_no_such_reference") . " "
                . $stringValue;

            return array('warning' => $warning);
        }

        return $referenceIds;
    }

    public function getExportValue(): string
    {
        $values = $this->getValue();
        $names = array();
        foreach ($values as $value) {
            if ($value) {
                $ref_rec = ilDclCache::getRecordCache($value);
                $names[] = $ref_rec->getRecordField($this->getField()->getFieldRef())->getValue();
            }
        }
        $string = "";
        foreach ($names as $name) {
            $string .= $name . ", ";
        }
        if (!count($names)) {
            return "";
        }
        $string = substr($string, 0, -2);

        return $string;
    }

    /**
     * This method tries to get as many valid references out of a string separated by commata. This is problematic as a string value could contain commata itself.
     * It is optimized to work with an exported list from this DataCollection. And works fine in most cases. Only areference list with the values "hello" and "hello, world"
     * Will mess with it.
     * @return int[]
     */
    protected function getReferencesFromString(string $stringValues): array
    {
        $slicedStrings = explode(", ", $stringValues);
        $slicedReferences = array();
        $resolved = 0;
        for ($i = 0; $i < count($slicedStrings); $i++) {
            //try to find a reference since the last resolved value separated by a comma.
            // $i = 1; $resolved = 0; $string = "hello, world, gaga" -> try to match "hello, world".
            $searchString = implode(array_slice($slicedStrings, $resolved, $i - $resolved + 1));
            if ($ref = $this->getReferenceFromValue($searchString)) {
                $slicedReferences[] = $ref;
                $resolved = $i;
                continue;
            }

            //try to find a reference with the current index.
            // $i = 1; $resolved = 0; $string = "hello, world, gaga" -> try to match "world".
            $searchString = $slicedStrings[$i];
            if ($ref = $this->getReferenceFromValue($searchString)) {
                $slicedReferences[] = $ref;
                $resolved = $i;
            }
        }

        return $slicedReferences;
    }
}
