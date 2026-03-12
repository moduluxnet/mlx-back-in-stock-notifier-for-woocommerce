document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('bis-subscribe-btn');
    const wrapper = document.getElementById('bis-wrapper');
    const subMsg = document.getElementById('bis-subscribed-msg');
    const formMsg = document.getElementById('bis-form-msg');
    
    // Array of IDs the user is currently subscribed to
    const userSubs = mlx_bis_data.userSubs || [];

    if (btn) {
        btn.addEventListener('click', function() {
            const currentId = parseInt(btn.dataset.id);
            const data = new FormData();
            data.append('action', 'bis_subscribe');
            data.append('product_id', currentId);
            data.append('nonce', btn.dataset.nonce);

            fetch(wc_add_to_cart_params.ajax_url, {
                method: 'POST',
                body: data
            }).then(() => {
                btn.innerText = mlx_bis_data.notifyText;
                btn.disabled = true;
                userSubs.push(currentId); // Add to local state so variation toggling remembers it
            });
        });
    }

    // WooCommerce Variable Product JS Hook
    if (typeof jQuery !== 'undefined') {
        jQuery('.variations_form').on('found_variation', function(event, variation) {
            if (wrapper) {
                // If the selected variation is out of stock AND cannot be backordered
                if (!variation.is_in_stock) {
                    wrapper.style.display = 'block';
                    if (btn) btn.dataset.id = variation.variation_id; // Swap to Variation ID
                    
                    // Check if they are already subscribed to this specific variation
                    if (userSubs.includes(variation.variation_id)) {
                        if(subMsg) subMsg.style.display = 'block';
                        if(formMsg) formMsg.style.display = 'none';
                    } else {
                        if(subMsg) subMsg.style.display = 'none';
                        if(formMsg) formMsg.style.display = 'block';
                        if(btn) {
                            btn.innerText = mlx_bis_data.notifyText;
                            btn.disabled = false;
                        }
                    }
                } else {
                    wrapper.style.display = 'none'; // Hide if in stock
                }
            }
        });

        // Hide the form if the user clears their selection
        jQuery('.variations_form').on('hide_variation reset_data', function() {
            if (wrapper) wrapper.style.display = 'none';
        });
    }
});