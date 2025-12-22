jQuery(document).ready(function($) {
    $('.gs-purge-cache').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to purge the entire cache?')) {
            return;
        }
        
        var $button = $(this);
        $button.text('Purging...');
        
        $.ajax({
            url: gsCachePurge.ajax_url,
            type: 'POST',
            data: {
                action: 'gs_purge_litespeed',
                nonce: gsCachePurge.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.text('? Purged!');
                    setTimeout(function() {
                        $button.html('?? Purge Cache');
                    }, 2000);
                } else {
                    alert('Error: ' + response.data);
                    $button.html('?? Purge Cache');
                }
            },
            error: function() {
                alert('AJAX error occurred');
                $button.html('?? Purge Cache');
            }
        });
    });
});