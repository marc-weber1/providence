<?php
/**
 * This file is part of PHPWord - A pure PHP library for reading and writing
 * word processing documents.
 *
 * PHPWord is free software distributed under the terms of the GNU Lesser
 * General Public License version 3 as published by the Free Software Foundation.
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code. For the full list of
 * contributors, visit https://github.com/PHPOffice/PHPWord/contributors.
 *
 * @link        https://github.com/PHPOffice/PHPWord
 * @copyright   2010-2014 PHPWord contributors
 * @license     http://www.gnu.org/licenses/lgpl.txt LGPL version 3
 */

namespace PhpOffice\PhpWord\Element;

use PhpOffice\PhpWord\Media;
use PhpOffice\PhpWord\PhpWord;

/**
 * Container abstract class
 *
 * @method Text addText($text, $fStyle = null, $pStyle = null)
 * @method TextRun addTextRun($pStyle = null)
 * @method Link addLink($target, $text = null, $fStyle = null, $pStyle = null)
 * @method PreserveText addPreserveText($text, $fStyle = null, $pStyle = null)
 * @method void addTextBreak($count = 1, $fStyle = null, $pStyle = null)
 * @method ListItem addListItem($text, $depth = 0, $fStyle = null, $listStyle = null, $pStyle = null)
 * @method ListItemRun addListItemRun($depth = 0, $listStyle = null, $pStyle = null)
 * @method Table addTable($style = null)
 * @method Image addImage($source, $style = null, $isWatermark = false)
 * @method Object addObject($source, $style = null)
 * @method Footnote addFootnote($pStyle = null)
 * @method Endnote addEndnote($pStyle = null)
 * @method CheckBox addCheckBox($name, $text, $fStyle = null, $pStyle = null)
 * @method TextBox addTextBox($style = null)
 * @method Field addField($type = null, $properties = array(), $options = array())
 * @method Line addLine($lineStyle = null)
 *
 * @since 0.10.0
 */
abstract class AbstractContainer extends AbstractElement
{
    /**
     * Elements collection
     *
     * @var array
     */
    protected $elements = array();

    /**
     * Container type Section|Header|Footer|Footnote|Endnote|Cell|TextRun|TextBox|ListItemRun
     *
     * @var string
     */
    protected $container;

    /**
     * Magic method to catch all 'addElement' variation
     *
     * This removes addText, addTextRun, etc. When adding new element, we have to
     * add the model in the class docblock with `@method`.
     *
     * Warning: This makes capitalization matters, e.g. addCheckbox or addcheckbox won't work.
     *
     * @param mixed $function
     * @param mixed $args
     * @return \PhpOffice\PhpWord\Element\AbstractElement
     */
    public function __call($function, $args)
    {
        $elements = array('Text', 'TextRun', 'Link', 'PreserveText', 'TextBreak',
            'ListItem', 'ListItemRun', 'Table', 'Image', 'Object', 'Footnote',
            'Endnote', 'CheckBox', 'TextBox', 'Field', 'Line');
        $functions = array();
        for ($i = 0; $i < count($elements); $i++) {
            $functions[$i] = 'add' . $elements[$i];
        }

        // Run valid `add` command
        if (in_array($function, $functions)) {
            $element = str_replace('add', '', $function);

            // Special case for TextBreak
            // @todo Remove the `$count` parameter in 1.0.0 to make this element similiar to other elements?
            if ($element == 'TextBreak') {
                @list($count, $fontStyle, $paragraphStyle) = $args; // Suppress error
                if ($count === null) {
                    $count = 1;
                }
                for ($i = 1; $i <= $count; $i++) {
                    $this->addElement($element, $fontStyle, $paragraphStyle);
                }

            // All other elements
            } else {
                array_unshift($args, $element); // Prepend element name to the beginning of args array
                return call_user_func_array(array($this, 'addElement'), $args);
            }
        }

        return null;
    }

    /**
     * Add element
     *
     * Each element has different number of parameters passed
     *
     * @param string $elementName
     * @return \PhpOffice\PhpWord\Element\AbstractElement
     */
    protected function addElement($elementName)
    {
        $elementClass = __NAMESPACE__ . '\\' . $elementName;
        $this->checkValidity($elementName);

        // Get arguments
        $args = func_get_args();
        $withoutP = in_array($this->container, array('TextRun', 'Footnote', 'Endnote', 'ListItemRun', 'Field'));
        if ($withoutP && ($elementName == 'Text' || $elementName == 'PreserveText')) {
            $args[3] = null; // Remove paragraph style for texts in textrun
        }
        $source = '';
        if (count($args) > 1) {
            $source = $args[1];
        }

        // Create element using reflection
        $reflection = new \ReflectionClass($elementClass);
        $elementArgs = $args;
        array_shift($elementArgs); // Shift the $elementName off the beginning of array

        /** @var \PhpOffice\PhpWord\Element\AbstractElement $element Type hint */
        $element = $reflection->newInstanceArgs($elementArgs);

        // Set nested level and relation Id
        $this->setElementNestedLevel($element);
        $this->setElementRelationId($element, $elementName, $source);

        // Set other properties and add element into collection
        $element->setDocPart($this->getDocPart(), $this->getDocPartId());
        $element->setElementIndex($this->countElements() + 1);
        $element->setElementId();
        $element->setPhpWord($this->phpWord);

        $this->elements[] = $element;

        return $element;
    }

    /**
     * Get all elements
     *
     * @return array
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * Count elements
     *
     * @return int
     */
    public function countElements()
    {
        return count($this->elements);
    }

    /**
     * Set element nested level based on container; add one when it's inside a cell
     */
    private function setElementNestedLevel(AbstractElement $element)
    {
        if ($this->container == 'Cell') {
            $element->setNestedLevel($this->getNestedLevel() + 1);
        } else {
            $element->setNestedLevel($this->getNestedLevel());
        }
    }

    /**
     * Set relation Id
     *
     * @param string $elementName
     * @param string $source
     */
    private function setElementRelationId(AbstractElement $element, $elementName, $source)
    {
        $mediaContainer = $this->getMediaContainer();
        $hasMediaRelation = in_array($elementName, array('Link', 'Image', 'Object'));
        $hasOtherRelation = in_array($elementName, array('Footnote', 'Endnote', 'Title'));

        // Set relation Id for media elements (link, image, object; legacy of OOXML)
        // Only Image that needs to be passed to Media class
        if ($hasMediaRelation) {
            /** @var \PhpOffice\PhpWord\Element\Image $element Type hint */
            $image = ($elementName == 'Image') ? $element : null;
            $rId = Media::addElement($mediaContainer, strtolower($elementName), $source, $image);
            $element->setRelationId($rId);
        }

        // Set relation Id for icon of object element
        if ($elementName == 'Object') {
            /** @var \PhpOffice\PhpWord\Element\Object $element Type hint */
            $rIdIcon = Media::addElement($mediaContainer, 'image', $element->getIcon(), new Image($element->getIcon()));
            $element->setImageRelationId($rIdIcon);
        }

        // Set relation Id for elements that will be registered in the Collection subnamespaces
        if ($hasOtherRelation && $this->phpWord instanceof PhpWord) {
            $addMethod = "add{$elementName}";
            $rId = $this->phpWord->$addMethod($element);
            $element->setRelationId($rId);
        }
    }

    /**
     * Check if a method is allowed for the current container
     *
     * @param string $method
     * @return bool
     * @throws \BadMethodCallException
     */
    private function checkValidity($method)
    {
        // Valid containers for each element
        $allContainers = array(
            'Section', 'Header', 'Footer', 'Footnote', 'Endnote',
            'Cell', 'TextRun', 'TextBox', 'ListItemRun',
        );
        $validContainers = array(
            'Text'          => $allContainers,
            'Link'          => $allContainers,
            'TextBreak'     => $allContainers,
            'Image'         => $allContainers,
            'Object'        => $allContainers,
            'Field'         => $allContainers,
            'Line'          => $allContainers,
            'TextRun'       => array('Section', 'Header', 'Footer', 'Cell', 'TextBox'),
            'ListItem'      => array('Section', 'Header', 'Footer', 'Cell', 'TextBox'),
            'ListItemRun'   => array('Section', 'Header', 'Footer', 'Cell', 'TextBox'),
            'Table'         => array('Section', 'Header', 'Footer', 'Cell', 'TextBox'),
            'CheckBox'      => array('Section', 'Header', 'Footer', 'Cell'),
            'TextBox'       => array('Section', 'Header', 'Footer', 'Cell'),
            'Footnote'      => array('Section', 'TextRun', 'Cell'),
            'Endnote'       => array('Section', 'TextRun', 'Cell'),
            'PreserveText'  => array('Header', 'Footer', 'Cell'),
        );
        // Special condition, e.g. preservetext can only exists in cell when
        // the cell is located in header or footer
        $validSubcontainers = array(
            'PreserveText'  => array(array('Cell'), array('Header', 'Footer')),
            'Footnote'      => array(array('Cell', 'TextRun'), array('Section')),
            'Endnote'       => array(array('Cell', 'TextRun'), array('Section')),
        );

        // Check if a method is valid for current container
        if (array_key_exists($method, $validContainers)) {
            if (!in_array($this->container, $validContainers[$method])) {
                throw new \BadMethodCallException("Cannot add $method in $this->container.");
            }
        }
        // Check if a method is valid for current container, located in other container
        if (array_key_exists($method, $validSubcontainers)) {
            $rules = $validSubcontainers[$method];
            $containers = $rules[0];
            $allowedDocParts = $rules[1];
            foreach ($containers as $container) {
                if ($this->container == $container && !in_array($this->getDocPart(), $allowedDocParts)) {
                    throw new \BadMethodCallException("Cannot add $method in $this->container.");
                }
            }
        }

        return true;
    }

    /**
     * Return media element (image, object, link) container name
     *
     * @return string section|headerx|footerx|footnote|endnote
     */
    private function getMediaContainer()
    {
        $partName = $this->container;
        if (in_array($partName, array('Cell', 'TextRun', 'TextBox', 'ListItemRun'))) {
            $partName = $this->getDocPart();
        }
        if ($partName == 'Header' || $partName == 'Footer') {
            $partName .= $this->getDocPartId();
        }

        return strtolower($partName);
    }

    /**
     * Add memory image element
     *
     * @param string $src
     * @param mixed $style
     * @return \PhpOffice\PhpWord\Element\Image
     * @deprecated 0.9.0
     * @codeCoverageIgnore
     */
    public function addMemoryImage($src, $style = null)
    {
        return $this->addImage($src, $style);
    }

    /**
     * Create textrun element
     *
     * @param mixed $paragraphStyle
     * @return \PhpOffice\PhpWord\Element\TextRun
     * @deprecated 0.10.0
     * @codeCoverageIgnore
     */
    public function createTextRun($paragraphStyle = null)
    {
        return $this->addTextRun($paragraphStyle);
    }

    /**
     * Create footnote element
     *
     * @param mixed $paragraphStyle
     * @return \PhpOffice\PhpWord\Element\Footnote
     * @deprecated 0.10.0
     * @codeCoverageIgnore
     */
    public function createFootnote($paragraphStyle = null)
    {
        return $this->addFootnote($paragraphStyle);
    }
}