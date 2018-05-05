<?php

class SearchResultsTableViewRowData
{
    protected $columnsData;
    public $elementValue;
    protected $hierarchyElements;
    public $itemThumbnailHtml;
    protected $searchResults;

    public function __construct($item, SearchResultsTableView $searchResults)
    {
        $this->searchResults = $searchResults;
        $this->columnsData = $searchResults->getColumnsData();
        $this->hierarchyElements = SearchConfig::getOptionDataForTreeView();
        $this->initializeData($item);
    }

    protected function filterHierarchicalElementText($elementId, $text)
    {
        $isHierarchyElement = array_key_exists($elementId, $this->hierarchyElements);
        if ($isHierarchyElement)
        {
            $index = strrpos($text, ',', -1);

            if ($index !== false)
            {
                // Filter out the ancestry to leave just the leaf text.
                $text = trim(substr($text, $index + 1));
            }
        }
        return $text;
    }

    protected function generateDateRange()
    {
        $yearStartElementName = CommonConfig::getOptionTextForYearStart();
        $yearEndElementName = CommonConfig::getOptionTextForYearEnd();

        if (empty($yearStartElementName) || empty($yearEndElementName) || !isset($this->elementValue['Date']))
        {
            // This feature is only support for installations that have all three date elements.
            return;
        }

        $date = $this->elementValue['Date']['text'];
        $yearStartText = $this->elementValue[$yearStartElementName]['text'];
        $yearEndText = $this->elementValue[$yearEndElementName]['text'];

        if (empty($date) && !empty($yearStartText))
        {
            // The date is empty so show the year start/end range.
            $this->elementValue['Date']['text'] = "$yearStartText - $yearEndText";
        }
    }

    protected function generateDescription()
    {
        // Get the description text, making sure that the Description element is defined.
        $descriptionText = isset($this->elementValue['Description']['text']) ? $this->elementValue['Description']['text'] : '';

        // Shorten the description text if it's too long.
        $maxLength = 250;
        $this->elementValue['Description']['text'] = str_replace('<br />', '', $descriptionText);
        $descriptionText = $this->elementValue['Description']['text'];
        if (strlen($descriptionText) > $maxLength)
        {
            // Truncate the description at whitespace and add an elipsis at the end.
            $shortText = preg_replace("/^(.{1,$maxLength})(\\s.*|$)/s", '\\1', $descriptionText);
            $shortTextLength = strlen($shortText);
            $remainingText = '<span class="search-more-text">' . substr($descriptionText, $shortTextLength) . '</span>';
            $remainingText .= '<span class="search-show-more"> ['. __('show more') . ']</span>';
            $this->elementValue['Description']['text'] = $shortText . $remainingText;
        }
        $this->elementValue['Description']['detail'] = $this->searchResults->emitFieldDetail('Description', $this->elementValue['Description']['text']);
    }

    protected function generateThumbnailHtml($item)
    {
        $itemPreview = new ItemPreview($item);
        $this->itemThumbnailHtml = $itemPreview->emitItemHeader();
        $this->itemThumbnailHtml .= $itemPreview->emitItemThumbnail(false);
    }

    protected function generateTitles($item)
    {
        // Create a link for the Title followed by a list of AKA (Also Known As) titles.
        $titleLink = link_to_item(ItemMetadata::getItemTitle($item));
        $this->elementValue['Title']['text'] = $titleLink;

        $titles = ItemMetadata::getAllElementTextsForElementName($item, 'Title');
        foreach ($titles as $key => $title)
        {
            if ($key == 0)
            {
                continue;
            }
            $this->elementValue['Title']['text'] .= '<div class="search-title-aka">' . html_escape($title) . '</div>';
        }
    }

    public static function getElementDetail($data, $elementName)
    {
        return $data->elementValue[$elementName]['detail'];
    }

    protected function getElementTextsAsHtml($elementId, $elementTexts, $filtered)
    {
        $texts = '';
        foreach ($elementTexts as $key => $elementText)
        {
            if ($key != 0)
            {
                $texts .= '<br/>';
            }

            $text = $filtered ? $this->filterHierarchicalElementText($elementId, $elementText) : $elementText;
            $texts .= html_escape($text);
        }

        return $texts;
    }

    protected function initializeData($item)
    {
        $this->elementValue = array();

        $this->readMetadata($item);
        $this->generateDescription();
        $this->generateDateRange();
        $this->generateTitles($item);
        $this->generateThumbnailHtml($item);
    }

    protected function readMetadata($item)
    {
        foreach ($this->columnsData as $elementId => $column)
        {
            $elementName = $column['name'];

            if ($elementName != 'Title')
            {
                $elementTexts = ItemMetadata::getAllElementTextsForElementName($item, $elementName);
                $fullText = $this->getElementTextsAsHtml($elementId, $elementTexts, false);
                $filteredText =  $this->getElementTextsAsHtml($elementId, $elementTexts, true);

                if ( $elementName != 'Description')
                {
                    $this->elementValue[$elementName]['detail'] = $this->searchResults->emitFieldDetail($column['alias'], $fullText);
                }

                if ($item->public == 0 && $elementName == ItemMetadata::getIdentifierElementName())
                {
                    // Indicate that this item is private.
                    $filteredText .= '*';
                }
            }

            $this->elementValue[$elementName]['text'] = $filteredText;
        }

        // Create a psuedo element value for tags since there is no actual tags element.
        $tags = metadata('item', 'has tags') ? tag_string('item', 'find') : '';
        $this->elementValue['<tags>']['text'] = '';
        $this->elementValue['<tags>']['detail'] = $this->searchResults->emitFieldDetail(__('Tags'),  $tags);
    }
}