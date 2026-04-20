<?php

namespace App\Program\Xml;

use SimpleXMLElement;

class SimpleXMLElementExtended extends SimpleXMLElement
{
	public function addCData(string $cdata_text): void
	{
		$node = dom_import_simplexml($this);
		if ($node) {
			$no = $node->ownerDocument;
			$node->appendChild($no->createCDATASection($cdata_text));
		}
	}

	public function addChildWithCDATA(string $name, ?string $value = null): SimpleXMLElementExtended
	{
		$new_child = $this->addChild($name);
		if ($new_child !== null) {
			$node = dom_import_simplexml($new_child);
			if ($node) {
				$no = $node->ownerDocument;
				$node->appendChild($no->createCDATASection($value));
			}
		}
		return $new_child;
	}
}
