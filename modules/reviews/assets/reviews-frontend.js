/**
 * Shaped Reviews Frontend
 *
 * Handles filter buttons and Load More pagination for the standalone reviews grid.
 * No jQuery dependency - vanilla JavaScript only.
 */
(function() {
    'use strict';

    const ShapedReviews = {
        container: null,
        grid: null,
        pagination: null,
        filterButtons: null,
        loadMoreBtn: null,

        currentProvider: 'all',
        currentPage: 1,
        perPage: 3,
        total: 0,
        isLoading: false,

        /**
         * Initialize the reviews module
         */
        init: function() {
            this.container = document.querySelector('.shaped-reviews-container');
            if (!this.container) return;

            this.grid = this.container.querySelector('.shaped-reviews-grid');
            this.pagination = this.container.querySelector('.shaped-reviews-pagination');
            this.filterButtons = this.container.querySelectorAll('.shaped-filter-btn');
            this.loadMoreBtn = this.container.querySelector('.shaped-load-more-btn');

            // Read initial state from data attributes
            this.currentProvider = this.container.dataset.provider || 'all';
            this.currentPage = parseInt(this.container.dataset.page, 10) || 1;
            this.perPage = parseInt(this.container.dataset.perPage, 10) || 3;
            this.total = parseInt(this.container.dataset.total, 10) || 0;

            // Check URL for provider parameter
            const urlProvider = this.getUrlParam('provider');
            if (urlProvider && urlProvider !== this.currentProvider) {
                this.currentProvider = urlProvider;
                // Update active filter button
                this.updateFilterButtons();
            }

            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            // Filter button clicks
            this.filterButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const provider = btn.dataset.provider;
                    if (provider !== this.currentProvider && !this.isLoading) {
                        this.onFilterClick(provider);
                    }
                });
            });

            // Load More button click
            if (this.loadMoreBtn) {
                this.loadMoreBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!this.isLoading) {
                        this.onLoadMore();
                    }
                });
            }

            // Handle browser back/forward
            window.addEventListener('popstate', (e) => {
                const provider = this.getUrlParam('provider') || 'all';
                if (provider !== this.currentProvider) {
                    this.currentProvider = provider;
                    this.currentPage = 1;
                    this.updateFilterButtons();
                    this.fetchAndReplaceGrid();
                }
            });
        },

        /**
         * Handle filter button click
         */
        onFilterClick: function(provider) {
            this.currentProvider = provider;
            this.currentPage = 1;

            // Update URL
            this.updateUrl(provider);

            // Update button states
            this.updateFilterButtons();

            // Fetch and replace grid
            this.fetchAndReplaceGrid();
        },

        /**
         * Handle Load More button click
         */
        onLoadMore: function() {
            this.currentPage++;
            this.fetchAndAppendCards();
        },

        /**
         * Update filter button active states
         */
        updateFilterButtons: function() {
            this.filterButtons.forEach(btn => {
                const isActive = btn.dataset.provider === this.currentProvider;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        },

        /**
         * Fetch reviews and replace entire grid (for filter changes)
         */
        fetchAndReplaceGrid: function() {
            this.setLoading(true);

            this.fetchReviews(this.currentProvider, 1)
                .then(response => {
                    if (response.success && response.data) {
                        // Replace grid content
                        this.grid.innerHTML = response.data.html || '<p class="shaped-reviews-empty">No reviews found for this filter.</p>';

                        // Update state
                        this.currentPage = 1;
                        this.total = response.data.total || 0;

                        // Update container data attributes
                        this.container.dataset.provider = this.currentProvider;
                        this.container.dataset.page = this.currentPage;
                        this.container.dataset.total = this.total;

                        // Update Load More button visibility
                        this.updateLoadMoreButton(response.data.has_more);
                    } else {
                        console.error('Failed to fetch reviews:', response);
                        this.grid.innerHTML = '<p class="shaped-reviews-empty">Error loading reviews. Please try again.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching reviews:', error);
                    this.grid.innerHTML = '<p class="shaped-reviews-empty">Error loading reviews. Please try again.</p>';
                })
                .finally(() => {
                    this.setLoading(false);
                });
        },

        /**
         * Fetch reviews and append to grid (for Load More)
         */
        fetchAndAppendCards: function() {
            this.setLoading(true);

            this.fetchReviews(this.currentProvider, this.currentPage)
                .then(response => {
                    if (response.success && response.data && response.data.html) {
                        // Append new cards
                        this.grid.insertAdjacentHTML('beforeend', response.data.html);

                        // Update container data
                        this.container.dataset.page = this.currentPage;

                        // Update Load More button visibility
                        this.updateLoadMoreButton(response.data.has_more);
                    } else {
                        // No more reviews or error - hide button
                        this.updateLoadMoreButton(false);
                        // Revert page number since fetch failed
                        this.currentPage--;
                    }
                })
                .catch(error => {
                    console.error('Error loading more reviews:', error);
                    // Revert page number
                    this.currentPage--;
                })
                .finally(() => {
                    this.setLoading(false);
                });
        },

        /**
         * Fetch reviews via AJAX
         */
        fetchReviews: function(provider, page) {
            const nonce = this.container.querySelector('input[name="shaped_reviews_nonce"]');

            const formData = new FormData();
            formData.append('action', 'shaped_load_more_reviews');
            formData.append('provider', provider);
            formData.append('page', page);
            formData.append('nonce', nonce ? nonce.value : '');

            return fetch(shapedReviewsData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json());
        },

        /**
         * Update Load More button visibility and state
         */
        updateLoadMoreButton: function(hasMore) {
            if (!this.pagination) return;

            if (hasMore) {
                // Show or create the button
                if (!this.loadMoreBtn) {
                    this.pagination.innerHTML = `
                        <button type="button" class="shaped-load-more-btn" data-page="${this.currentPage}">
                            <span class="shaped-load-more-text">Load More</span>
                            <span class="shaped-load-more-spinner" style="display:none;"></span>
                        </button>
                    `;
                    this.loadMoreBtn = this.pagination.querySelector('.shaped-load-more-btn');
                    this.loadMoreBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (!this.isLoading) {
                            this.onLoadMore();
                        }
                    });
                }
                this.pagination.style.display = 'flex';
                this.loadMoreBtn.dataset.page = this.currentPage;
            } else {
                // Hide pagination
                this.pagination.style.display = 'none';
            }
        },

        /**
         * Set loading state
         */
        setLoading: function(loading) {
            this.isLoading = loading;

            if (this.loadMoreBtn) {
                const text = this.loadMoreBtn.querySelector('.shaped-load-more-text');
                const spinner = this.loadMoreBtn.querySelector('.shaped-load-more-spinner');

                this.loadMoreBtn.disabled = loading;

                if (text) text.style.display = loading ? 'none' : 'inline';
                if (spinner) spinner.style.display = loading ? 'inline-block' : 'none';
            }

            // Add loading class to grid for potential CSS animations
            if (this.grid) {
                this.grid.classList.toggle('is-loading', loading);
            }
        },

        /**
         * Update URL with provider parameter
         */
        updateUrl: function(provider) {
            const url = new URL(window.location.href);

            if (provider === 'all') {
                url.searchParams.delete('provider');
            } else {
                url.searchParams.set('provider', provider);
            }

            window.history.pushState({ provider: provider }, '', url.toString());
        },

        /**
         * Get URL parameter value
         */
        getUrlParam: function(name) {
            const params = new URLSearchParams(window.location.search);
            return params.get(name);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ShapedReviews.init());
    } else {
        ShapedReviews.init();
    }

    // Expose for debugging if needed
    window.ShapedReviews = ShapedReviews;

})();
