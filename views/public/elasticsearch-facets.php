<?php
$avantElasticsearchFacets = new AvantElasticsearchFacets();
$facetDefinitions = $avantElasticsearchFacets->getFacetDefinitions();
$queryString = $avantElasticsearchFacets->createQueryStringWithFacets($query);

$appliedFacets = $query['facet'];
$facetsAreApplied = count($appliedFacets) > 0;
$appliedFacetValues = array();

$findUrl = get_view()->url('/find');
?>

<?php if ($facetsAreApplied): ?>
    <div id="elasticsearch-filters-active">
        <div class="elasticsearch-facet-section">Applied Filters
            <a class="elasticsearch-facet-reset"
               href="<?php echo get_view()->url('/find') . '?query=' . urlencode($query['query']); ?>">[Reset]</a>
        </div>
        <?php
        $appliedFilters = '';

        foreach ($appliedFacets as $facetId => $facetValues)
        {
            if (!isset($facetDefinitions[$facetId]))
            {
                // This should only happen if the query string syntax in invalid because someone edited or mistyped it.
                break;
            }

            $facetName = htmlspecialchars($facetDefinitions[$facetId]['name']);
            $appliedFilters .= '<div class="elasticsearch-facet-name">' . $facetName . '</div>';
            $appliedFilters .= '<ul>';

            foreach ($facetValues as $facetValue)
            {
                $appliedFacetValues[] = $facetValue;
                $resetLink = $avantElasticsearchFacets->createRemoveFacetLink($queryString, $facetId, $facetValue);
                $appliedFilters .= '<li>';
                $appliedFilters .= "<i>$facetValue</i>";
                $appliedFilters .= '<a href="' . $findUrl . '?' . $resetLink . '"> [&#10006;]</a>';
                $appliedFilters .= '</li>';
            }

            $appliedFilters .= '</ul>';
        }
        echo $appliedFilters;
        ?>
    </div>
<?php endif; ?>

<div id="elasticsearch-filters">
    <div class="elasticsearch-facet-section">Filters</div>
    <?php

    foreach ($facetDefinitions as $facetId => $facetDefinition)
    {
        $buckets = $aggregations[$facetId]['buckets'];

        if (count($buckets) == 0 || $facetDefinition['hidden'])
        {
            // Don't display empty buckets or hidden facets.
            continue;
        }

        echo '<div class="elasticsearch-facet-name">' . $facetDefinition['name'] . '</div>';

        $filters = '';
        $buckets = $aggregations[$facetId]['buckets'];

        foreach ($buckets as $bucket)
        {
            $bucketValue = $bucket['key'];

            $isLeaf = strpos($bucketValue, ',') !== false;

            if (!$facetsAreApplied)
            {
                if ($isLeaf)
                {
                    // Don't show leafs until at least one facet is applied.
                    continue;
                }
            }

            $applied = in_array($bucketValue, $appliedFacetValues);
            $text = htmlspecialchars($bucketValue);
            $count = ' (' . $bucket['doc_count'] . ')';

            if ($applied)
            {
                // Don't provide a link for a facet that's already been applied.
                $filter = $text;
            }
            else
            {
                // Create a link that the user can click to apply this facet.
                $filterLink = $avantElasticsearchFacets->createAddFacetLink($queryString, $facetId, $bucketValue);
                $facetUrl = $findUrl . '?' . $filterLink;
                $filter = '<a href="' . $facetUrl . '">' . $text . '</a>' . $count;
            }

            $class = '';
            if ($facetsAreApplied && $facetDefinition['show_root'])
            {
                // Add some styling when leafs appear under roots.
                $level = $isLeaf ? 'leaf' : 'root';
                $class = " class='elasticsearch-facet-$level'";
            }

            $filters .= "<li$class>$filter</li>";
        }

        echo "<ul>$filters</ul>";
    }
    ?>
</div>

