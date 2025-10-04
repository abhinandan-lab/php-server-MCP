// Global Search Functionality
const searchData = [
    { title: 'Home', url: 'index.html', desc: 'Framework overview and quick start' },
    { title: 'Getting Started', url: 'getting-started.html', desc: 'Docker setup and first API' },
    { title: 'Routing', url: 'routing.html', desc: 'Define API routes and endpoints' },
    { title: 'Controllers', url: 'controllers.html', desc: 'Create controller classes' },
    { title: 'Database', url: 'database.html', desc: 'Database connections and setup' },
    { title: 'RunQuery Guide', url: 'runquery-guide.html', desc: 'Complete database query guide' },
    { title: 'Migrations', url: 'migrations.html', desc: 'Database schema management' },
    { title: 'Validation & Security', url: 'validation.html', desc: 'Input validation and security' },
    { title: 'Testing', url: 'testing.html', desc: 'API testing with cURL and Postman' },
    { title: 'Function Reference', url: 'function-reference.html', desc: 'Searchable function database' }
];

document.addEventListener('DOMContentLoaded', function() {
    const globalSearch = document.getElementById('globalSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (globalSearch && searchResults) {
        globalSearch.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            
            if (query.length < 2) {
                searchResults.classList.remove('show');
                return;
            }
            
            const filtered = searchData.filter(item => 
                item.title.toLowerCase().includes(query) ||
                item.desc.toLowerCase().includes(query)
            );
            
            if (filtered.length > 0) {
                searchResults.innerHTML = filtered.map(item => `
                    <a href="${item.url}" class="search-result-item">
                        <div class="search-result-title">${item.title}</div>
                        <div class="search-result-desc">${item.desc}</div>
                    </a>
                `).join('');
                searchResults.classList.add('show');
            } else {
                searchResults.innerHTML = '<div class="search-result-item"><div class="search-result-desc">No results found</div></div>';
                searchResults.classList.add('show');
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!globalSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });
    }
});
