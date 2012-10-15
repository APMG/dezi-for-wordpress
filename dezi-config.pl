# Dezi server config for use with Wordpress plugin dezi-for-wordpress
{

    engine_config => {
        default_response_format => 'JSON',

        # could be any Search::OpenSearch::Engine::* class
        type => 'Lucy',

        # name of the index(es)
        index => [qw( dezi.index )],

        # which facets to calculate, and how many results to consider
        facets => {
            names       => [qw(  )],  # TODO derive f= param
            sample_size => 10_000,
        },

        # result attributes in response
        fields => [
            qw( id permalink numcomments categories categoriessrch tags tagssrch author author_s type date modified displaydate displaymodified )
        ],

        # options passed to indexer defined by Engine type (above)
        # defaults to SWISH::Prog::Lucy::Indexer->new
        indexer_config => {

            # see SWISH::Prog::Config
            # and http://swish-e.org/docs/swish-config.html
            config => {

                # searchable fields
                MetaNames =>
                    'id permalink numcomments categories categoriessrch tags tagssrch author author_s type date modified displaydate displaymodified',

                # attributes to store
                PropertyNames =>
                    'id permalink numcomments categories categoriessrch tags tagssrch author author_s type date modified displaydate displaymodified',

                # auto-vivify new fields based on POSTed docs.
                # use this if you want ElasticSearch-like effect.
                UndefinedMetaTags => 'auto',

                # treat unknown mime types as text/plain
                DefaultContents => 'TXT',

                # use English snowball stemmer
                FuzzyIndexingMode => 'Stemming_en1',

            },

            # store token positions to optimize snippet creation
            highlightable_fields => 1,
        },
    },

}
