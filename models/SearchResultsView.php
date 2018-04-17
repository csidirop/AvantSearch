<?php
class SearchResultsView
{
    const DEFAULT_KEYWORDS_CONDITION = 1;
    const DEFAULT_SEARCH_FILES = 0;
    const DEFAULT_SEARCH_TITLES = 0;
    const DEFAULT_VIEW = '1';

    const KEYWORD_CONDITION_ALL_WORDS = 1;
    const KEYWORD_CONDITION_CONTAINS = 2;
    const KEYWORD_CONDITION_BOOLEAN = 3;

    const DEFAULT_LIMIT = 25;
    const MAX_LIMIT = 200;

    protected $condition;
    protected $conditionName;
    protected $files;
    protected $isAdmin;
    protected $keywords;
    protected $results;
    protected $titles;
    protected $totalResults;
    protected $searchFilters;
    protected $sortField;
    protected $sortFieldName;
    protected $sortOrder;
    protected $subjectSearch;
    protected $viewId;
    protected $viewName;

    function __construct()
    {
        $this->searchFilters = new SearchResultsFilters($this);
        $this->isAdmin = is_allowed('Users', 'edit');
    }

    public static function createColumnClass($key, $tag)
    {
        $columnClass = str_replace(' ', '-', strtolower($key));
        $columnClass = str_replace('<', '', $columnClass);
        $columnClass = str_replace('>', '', $columnClass);
        $columnClass = str_replace('#', '', $columnClass);
        $columnClass = "search-$tag-$columnClass";
        return $columnClass;
    }

    public function emitClassAttribute($className1, $className2 = '')
    {
        $classAttribute = $className1;

        if ($classAttribute && $className2)
            $classAttribute .= ' ';

        if ($className2)
            $classAttribute .= $className2;

        if ($classAttribute)
            $classAttribute = 'class="' . $classAttribute . '"';

        return $classAttribute;
    }

    public static function emitFieldDetail($elementName, $text)
    {
        return $text ? "<span class=\"search-results-detail-element\">$elementName</span>:<span class=\"search-results-detail-text\">$text</span>" : '';
    }

    public function emitHeaderRow($headerColumns)
    {
        $sortField = $this->getSortField();
        $sortOrder = $this->getSortOrder();

        $headerRow = '';

        foreach ($headerColumns as $columnName => $headerColumn)
        {
            $columnLabel = $headerColumn['label'];
            $classes = $headerColumn['classes'];

            if ($headerColumn['sortable'])
            {
                $columnElementId = ItemMetadata::getElementIdForElementName($columnName);
                $params = $_GET;
                $params['sort'] = $columnElementId;
                $sortDirection = 'a';

                if ($sortField == $columnElementId)
                {
                    if ($sortOrder == 'd')
                    {
                        // Show the currently sorted column as descending, but set to sort ascending when clicked.
                        $sortClass = 'sortable desc';
                    }
                    else
                    {
                        // Show the currently sorted column as ascending, but set to sort descending when clicked.
                        $sortClass = 'sortable asc';
                        $sortDirection = 'd';
                    }
                }
                else
                {
                    // This is not the current column. Set it to sort ascending when clicked.
                    // Leave off the 'asc' class so that the ascending (up) arrow won't displayed except on hover.
                    $sortClass = 'sortable';
                }

                $params['order'] = $sortDirection;
                $url = html_escape(url(array(), null, $params));
                $classAttribute = self::emitClassAttribute($sortClass, $classes);
                $headerRow .= "<th $classAttribute><a href=\"$url\" class=\"search-link\">$columnLabel</a></th>" . PHP_EOL;
            }
            else
            {
                $classAttribute = $this->emitClassAttribute($classes);
                $headerRow .= "<th $classAttribute>$columnLabel</th>" . PHP_EOL;
            }
        }
        return $headerRow;
    }

    public function emitIndexEntryUrl($entry, $indexFieldElementId, $condition)
    {
        // Get the current query parameters.
        $params = $_GET;

        // Change from the current view to one that is appropriate when the user clicks an index.
        $params['view'] = SearchResultsViewFactory::getIndexTargetView();
        unset($params['index']);

        // Add a condition that the index field must exactly match the entry text.
        $index = isset($params['advanced']) ? count($params['advanced']) : 0;
        $params['advanced'][$index]['element_id'] = $indexFieldElementId;
        $params['advanced'][$index]['type'] = $condition;
        $params['advanced'][$index]['terms'] = $entry;

        // Rebuild the query string which now has all the original filter parameters plus the one just added.
        $queryString = http_build_query($params);
        return url("find?$queryString");
    }

    public function emitModifySearchButton()
    {
        if (!isset($this->subjectSearch))
            $this->subjectSearch = isset($_GET['subjects']);

        $text = __('Modify Search');
        $uri = url('find/' . ($this->subjectSearch ? 'subject' : 'advanced'));
        $action = $uri . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        $form = "<form id='modify-form' name='modify-form' action='$action' method='post' class='modify-search-button'>";
        $form .= "<button id='submit_modify' 'type='submit' value='Modify'>$text</button></form>";
        return $form;
    }

    public function emitSearchFilters($layoutIndicator, $paginationNav)
    {
        return $this->searchFilters->emitSearchFilters($layoutIndicator, $paginationNav);
    }

    public function getAdvancedSearchFields()
    {
        // Get the names of the private elements that the admin configured.
        $privateElementsData = json_decode(get_option('avantsearch_private_elements'), true);
        $privateFields = array();
        foreach ($privateElementsData as $elementId => $name)
        {
            $privateFields[$elementId] = $name;
        }

        // Get all the elements and then filter to leave only public elements.
        $allFields = get_table_options('Element', null, array('record_types' => array('Item', 'All'), 'sort' => 'alpha'));
        $publicFields = array_diff($allFields, $privateFields);

        $options = array('' => __('Select Below'));

        if ($this->isAdmin)
        {
            // When an admin is logged in, display the public fields first, then the private fields.
            // We do this so that commonly used public fields like Title don't end up at the very
            // bottom of the list and require scrolling to select.
            foreach ($publicFields as $key => $fieldName)
            {
                if (empty($key))
                {
                    // Skip "Select Below" that get_table_options inserts.
                    continue;
                }
                $value = $fieldName;
                $options[__('Public Fields')][$key] = $value;
            }
            foreach ($privateFields as $key => $fieldName)
            {
                $value = $fieldName;
                $options[__('Admin Fields')][$key] = $value;
            }
        }
        else
        {
            $options = $publicFields;
        }

        return $options;
    }

    public function getFilesOnlyOptions()
    {
        return array(
            '0' => __('All items'),
            '1' => __('Only items with images or files')
        );
    }

    public static function getIndexViewOptions($optionName)
    {
        $fields = explode(';', get_option($optionName));
        $fields = array_map('trim', $fields);

        $layoutDefinitions = SearchResultsTableView::getLayoutDefinitions();
        $elementNames = $layoutDefinitions['elements'];

        $options = array();

        foreach ($fields as $elementName)
        {
            if (empty(trim($elementName)))
            {
                continue;
            }

            if (!isset($elementNames[$elementName]))
            {
                continue;
            }

            $label = $elementNames[$elementName];

            $elementId = ItemMetadata::getElementIdForElementName($elementName);
            $options[$elementId] = $label;
        }

        return $options;
    }

    public function getKeywords()
    {
        if (isset($this->keywords))
            return $this->keywords;

        // Get keywords that were specified on the Advanced Search page.
        $this->keywords = isset($_GET['keywords']) ? $_GET['keywords'] : '';

        // Check if keywords came from the Simple Search text box.
        if (empty($this->keywords))
            $this->keywords = isset($_GET['query']) ? $_GET['query'] : '';

        return $this->keywords;
    }

    public function getKeywordsCondition()
    {
        if (isset($this->condition))
            return $this->condition;

        $this->condition = isset($_GET['condition']) ?  intval($_GET['condition']) : self::DEFAULT_KEYWORDS_CONDITION ;

        if (!array_key_exists($this->condition, $this->getKeywordsConditionOptions()))
            $this->condition = self::DEFAULT_KEYWORDS_CONDITION;

        return $this->condition;
    }

    public function getKeywordsConditionName()
    {
        if (isset($this->conditionName))
            return $this->conditionName;

        // Force the condition to be gotten if it hasn't been already;
        $condition = $this->getKeywordsCondition();

        $this->conditionName = $this->getKeywordsConditionOptions()[$condition];

        return $this->conditionName;
    }

    public function getKeywordsConditionOptions()
    {
        return array(
            self::KEYWORD_CONDITION_ALL_WORDS => __('All words'),
            self::KEYWORD_CONDITION_CONTAINS => __('Contains'),
            self::KEYWORD_CONDITION_BOOLEAN => __('Boolean')
        );
    }

    public function getKeywordSearchTitlesOptions()
    {
        return array(
            '0' => __('All fields'),
            '1' => __('Titles only')
        );
    }

    public function getResults()
    {
        return $this->results;
    }

    public function getResultsLimit()
    {
        if (isset($this->limit))
            return $this->limit;

        // First check for a cookie value.
        $this->limit = isset($_COOKIE['SEARCH-LIMIT']) ? intval($_COOKIE['SEARCH-LIMIT']) : 0;
        if ($this->limit > 0 && $this->limit <= self::MAX_LIMIT)
            return $this->limit;

        // Next check for a query string argument.
        $this->limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
        if ($this->limit > 0 && $this->limit <= self::MAX_LIMIT)
            return $this->limit;

        // Use the Omeka admin/appearance/edit-settings setting for Results Per Page.
        $this->limit = get_option('per_page_public');
        if ($this->limit > 0 && $this->limit <= self::MAX_LIMIT)
            return $this->limit;

        // Just to be safe, provide a hard default.
        $this->limit = self::DEFAULT_LIMIT;

        return $this->limit;
    }

    public function getSearchFiles()
    {
        if (isset($this->files))
            return $this->files;

        $this->files = isset($_GET['files']) ? intval($_GET['files'] == 1) : self::DEFAULT_SEARCH_FILES ;
        return $this->files;
    }

    public static function getSearchResultsMessage($count)
    {
        $s = $count == 1 ? __('item found') : __('items found');
        return "$count $s";
    }

    public function getSearchTitles()
    {
        if (isset($this->titles))
            return $this->titles;

        $this->titles = isset($_GET['titles']) ? intval($_GET['titles'] == 1) : self::DEFAULT_SEARCH_TITLES ;
        return $this->titles;
    }

    public function getSortField()
    {
        if (isset($this->sortField))
            return $this->sortField;

        $this->sortField = isset($_GET['sort']) ? intval($_GET['sort']) : 0;

        // Validate the sort field Id by attempting to get the field's name.
        $this->sortFieldName = ItemMetadata::getElementNameFromId($this->sortField);
        if (empty($this->sortFieldName))
        {
            // The Id is not valid. Use the Title as a default.
            $this->sortField = ItemMetadata::getTitleElementId();
            $this->sortFieldName = ItemMetadata::getTitleElementName();
        }
        return $this->sortField;
    }

    public function getSortFieldName()
    {
        if (!isset($this->sortField))
        {
            // Get the name by forcing a get of the sort field Id.
            $this->getSortField();
        }

        return $this->sortFieldName;
    }

    public function getSortOrder()
    {
        if (isset($this->sortOrder))
            return $this->sortOrder;

        $this->sortOrder = isset($_GET['order']) ? $_GET['order'] : 'a';
        return $this->sortOrder;
    }

    public function getTotalResults()
    {
        return $this->totalResults;
    }

    public function getViewId()
    {
        if (isset($this->viewId))
            return $this->viewId;

        $this->viewId = isset($_GET['view']) ? intval($_GET['view']) : self::DEFAULT_VIEW;

        if (!array_key_exists($this->viewId, $this->getViewOptions()))
            $this->viewId = self::DEFAULT_VIEW;

        return $this->viewId;
    }

    public function getViewName()
    {
        if (isset($this->viewName))
            return $this->viewName;

        // Force the view Id to be gotten if it hasn't been already;
        $viewName = $this->getViewId();

        $this->viewName = $this->getViewOptions()[$viewName];

        return $this->viewName;
    }

    public function getViewOptions()
    {
        return SearchResultsViewFactory::getViewOptions();
    }

    public function getViewShortName()
    {
        return SearchResultsViewFactory::getViewShortName($this->getViewId());
    }

    public function setResults($results)
    {
        $this->results = $results;
    }

    public function setTotalResults($totalResults)
    {
        $this->totalResults = $totalResults;
    }

    public function setViewId($viewId)
    {
        $this->viewId = $viewId;
    }
}