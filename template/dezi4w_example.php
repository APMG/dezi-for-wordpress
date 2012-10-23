<?php
/*
Template Name: Search
*/

// includes the example css file
add_action('wp_head', 'dezi4w_default_head');

?>

<?php get_header(); ?>
<div id="content">

<div class="dezi clearfix">

<?php
$results = dezi4w_search_results();
if (!isset($results['results']) || $results['results'] === NULL) {
?>
    <div class='dezi_noresult'>
     <h2>Sorry, search is unavailable right now</h2>
     <p>Try again later?</p>
    </div>
    
<?php
}
else {
?>

   <div class="dezi1 clearfix">
    <div class="dezi_search">
<?php if ($results['qtime']) { ?>
        <label class='dezi_response'>Response time: <span id="qrytime"><?php echo $results['qtime'] ?></span> s</label>
<?php }

    //if server id has been defined keep hold of it
    $server = $_GET['server'];
    if ($server) {
        $serverval = '<input name="server" type="hidden" value="'.$server.'" />';
    }

?>

      <form name="searchbox" method="get" id="searchbox" action="">
        <input id="qrybox" name="s" type="text" class="dezi_field" value="<?php echo $results['query'] ?>"/>
        <?php echo $serverval; ?>
        <input id="searchbtn" type="submit" value="Search" />
      </form>
    </div><!-- /dezi_search -->

        <?php if ($results['dym']) {
        printf("<div class='dezi_suggest'>Did you mean: <a href='%s'>%s</a> ?</div>", $results['dym']['link'], $results['dym']['term']);
    } ?>

   </div><!-- /dezi1 -->

   <div class="dezi2">

        <div class="dezi_results_header clearfix">
            <div class="dezi_results_headerL">

<?php if ($results['hits'] && $results['query'] && $results['qtime']) {
    if ($results['firstresult'] === $results['lastresult']) {
?>
    Displaying result <?php echo $results['firstresult'] ?> of <span id='resultcnt'><?php echo $results['hits'] ?></span> hits

<?php
    } 
    else {
?>
    Displaying results <?php echo $results['firstresult'] ?>-<?php echo $results['lastresult'] ?>
     of <span id='resultcnt'><?php echo $results['hits'] ?></span> hits
<?php
    }
} // hits && query && qtime
?>

            </div>
            <div class="dezi_results_headerR">
                <ol class="dezi_sort2">
                    <li class="dezi_sort_drop"><a href="<?php echo $results['sorting']['scoredesc'] ?>">Relevance<span></span></a></li>
                    <li><a href="<?php echo $results['sorting']['datedesc'] ?>">Newest</a></li>
                    <li><a href="<?php echo $results['sorting']['dateasc'] ?>">Oldest</a></li>
                    <li><a href="<?php echo $results['sorting']['commentsdesc'] ?>">Most Comments</a></li>
                    <li><a href="<?php echo $results['sorting']['commentsasc'] ?>">Least Comments</a></li>
                </ol>
                <div class="dezi_sort">Sort by:</div>
            </div>
        </div><?-- /dezi_results_header -->

        <div class="dezi_results">

<?php

    if ($results['hits'] === "0") {
?>
          <div class='dezi_noresult'>
            <h2>Sorry, no results were found.</h2>
            <h3>Perhaps you mispelled your search query, or need to try using broader search terms.</h3>
            <p>For example, instead of searching for 'Apple iPhone 3.0 3GS', try something simple like 'iPhone'.</p>
          </div>
<?php
    } 
    else {
?>
        <ol>
<?php
    foreach ($results['results'] as $result) {
?>
            <li onclick="window.location='<?php echo $result['permalink']?>'">
             <h2><a href="<?php echo $result['permalink'] ?>"><?php echo $result['title'] ?></a></h2>
             <p><?php echo $result['teaser'] ?> <a href="<?php echo $result['comment_link'] ?>">(comment match)</a></p>
             <label> By <a href="<?php echo $result['authorlink'] ?>"><?php echo $result['author']</a> 
              in <?php echo get_the_category_list( ', ', '', $result['id']) ?> <?php echo date('m/d/Y', strtotime($result['date'])) ?>
              - <a href="<?php echo $result['comment_link'] ?>"><?php echo $result['numcomments'] ?> comments</a>
             </label>
            </li>
<?php
    }
?>
    </ol>
    
<?php 
}   // end else 
?>

<?php if ($results['pager']) { ?>
        <div class='dezi_pages'>
<?php 
        $itemlinks = array();
        $pagecnt = 0;
        $pagemax = 10;
        $next = '';
        $prev = '';
        $found = false;
        foreach ($results['pager'] as $pageritm) {
            if ($pageritm['link']) {
                if ($found && $next === '') {
                    $next = $pageritm['link'];
                } 
                elseif ($found == false) {
                    $prev = $pageritm['link'];
                }

                $itemlinks[] = sprintf("<a href='%s'>%s</a>", $pageritm['link'], $pageritm['page']);
            } 
            else {
                $found = true;
                $itemlinks[] = sprintf("<a class='dezi_pages_on' href='%s'>%s</a>", $pageritm['link'], $pageritm['page']);
            }

            $pagecnt += 1;
            if ($pagecnt == $pagemax) {
                break;
            }
        }

        if ($prev !== '') {
            printf("<a href='%s'>Previous</a>", $prev);
        }

        foreach ($itemlinks as $itemlink) {
            echo $itemlink;
        }

        if ($next !== '') {
            printf("<a href='%s'>Next</a>", $next);
        }
?>
        </div><!-- /dezi_pages -->    
<?php 
}   // end pager 
?>


        </div><!-- /dezi_results -->
    </div><!-- /dezi2 -->

    <div class="dezi3">
        <ul class="dezi_facets">
          <li class="dezi_active">
            <ol>
    <?php if ($results['facets']['selected']) {
        foreach ( $results['facets']['selected'] as $selectedfacet) { ?>
             <li>
              <span></span>
              <a href="<?php echo $selectedfacet['removelink'] ?>"><?php echo $selectedfacet['name'] ?><b>x</b></a>
             </li>
<?php 
        } // end foreach
    } 
?>
           </ol>
          </li>

<?php if ($results['facets'] && $results['hits'] != 1) {
        foreach ($results['facets'] as $facet) {
            if (sizeof($facet["items"]) > 1) { //don't display facets with only 1 value
                printf("<li>\n<h3>%s</h3>\n", $facet['name']);
                dezi4w_print_facet_items($facet["items"], "<ol>", "</ol>", "<li>", "</li>", "<li><ol>", "</ol></li>", "<li>", "</li>");
                printf("</li>\n");
            }
        }
    } 
?>
        </ul>
    </div><!-- /dezi3 -->

  </div><!-- /dezi -->

</div><!-- /content -->
<?php

} // end else has results

get_footer();
