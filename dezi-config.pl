# Dezi server config for use with Wordpress plugin dezi-for-wordpress

# english stemmer by default -- do not need this if FuzzyIndexingMode
# is not set below
use Lingua::Stem::Snowball;
my $snowball = Lingua::Stem::Snowball->new(
    lang     => 'en',
    encoding => 'UTF-8',
);
my $stemmer = sub {
    my ( $qp, $term ) = @_;
    return $snowball->stem($term);
};

{

    engine_config => {
        default_response_format => 'JSON',

        # the Search::OpenSearch::Engine::* class
        type => 'Lucy',

        # name of the index(es)
        index => [qw( dezi.index )],

        # which facets to calculate, and how many results to consider
        facets => {
            names => [qw( categories tags author type  )]
            ,    # TODO derive f= param
            sample_size => 10_000,
        },

        # will break results html
        do_not_hilite => { map { $_ => 1 } qw( permalink ) },

        # how many seconds should facets be cached? 
        # short in dev, set to longer for better performance.
        cache_ttl => 30,

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

                # treat unknown mime types as text/plain
                DefaultContents => 'TXT',

                # use English snowball stemmer
                FuzzyIndexingMode => 'Stemming_en1',

            },

            # store token positions to optimize snippet creation
            highlightable_fields => 1,
        },
        parser_config => {
            query_dialect => 'Lucy',
            stemmer       => $stemmer,
        },
    },

}
