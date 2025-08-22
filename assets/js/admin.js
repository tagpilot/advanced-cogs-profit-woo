jQuery(function($) {
    $("#advanced-cogs-profit-woo-insert").click(function(ev) {
        ev.preventDefault();
        var table = this.parentNode.parentNode.parentNode.parentNode;
        $('tbody tr:last-child', table).clone().appendTo($('tbody', table));
        $('tbody tr:last-child :input', table).not(':button, :submit, :reset, :hidden')
            .val('').prop('checked', false).prop('selected', false)
            .each(function(i, inp) {
                var name = inp.name;
                var match = name.match(/\[(\d*)\]/i);
                $(inp).attr('name', name.replace(match[0], '[' + (parseInt(match[1]) + 1) + ']'))
            });
    });

    $("#advanced-cogs-profit-woo-table").on( "click", ".advanced-cogs-profit-woo-remove", function(ev) {
        ev.preventDefault();
        this.parentNode.parentNode.remove();
    });
});