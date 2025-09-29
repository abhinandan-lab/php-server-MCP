// PHP Light Framework Documentation JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality
    initSidebarToggle();
    initCodeCopyButtons();
    initSearchFunctionality();
    initScrollToTop();
    initActiveNavigation();
    initTableOfContents();
    initSmoothScrolling();
    initKeyboardShortcuts();
});

// Sidebar toggle for mobile
function initSidebarToggle() {
    // Create mobile menu button if it doesn't exist
    if (window.innerWidth <= 768 && !document.querySelector('.mobile-menu-btn')) {
        const menuBtn = document.createElement('button');
        menuBtn.className = 'mobile-menu-btn';
        menuBtn.innerHTML = '☰ Menu';
        menuBtn.style.cssText = `
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
        `;
        
        document.body.appendChild(menuBtn);
        
        menuBtn.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
            
            // Update button text
            if (sidebar.classList.contains('show')) {
                menuBtn.innerHTML = '✕ Close';
                menuBtn.style.background = 'var(--danger-color)';
            } else {
                menuBtn.innerHTML = '☰ Menu';
                menuBtn.style.background = 'var(--primary-color)';
            }
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.sidebar');
            if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.remove('show');
                menuBtn.innerHTML = '☰ Menu';
                menuBtn.style.background = 'var(--primary-color)';
            }
        });
    }
}

// Add copy buttons to code blocks
function initCodeCopyButtons() {
    const codeBlocks = document.querySelectorAll('pre code');
    
    codeBlocks.forEach(function(codeBlock) {
        const pre = codeBlock.parentElement;
        
        // Skip if button already exists
        if (pre.querySelector('.copy-code-btn')) return;
        
        // Create copy button
        const copyBtn = document.createElement('button');
        copyBtn.className = 'copy-code-btn';
        copyBtn.innerHTML = '📋 Copy';
        copyBtn.setAttribute('title', 'Copy code to clipboard');
        copyBtn.style.cssText = `
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 10;
        `;
        
        pre.style.position = 'relative';
        pre.appendChild(copyBtn);
        
        // Show button on hover
        pre.addEventListener('mouseenter', function() {
            copyBtn.style.opacity = '1';
        });
        
        pre.addEventListener('mouseleave', function() {
            copyBtn.style.opacity = '0';
        });
        
        // Copy functionality
        copyBtn.addEventListener('click', function() {
            const text = codeBlock.textContent;
            
            if (navigator.clipboard && window.isSecureContext) {
                // Use Clipboard API if available
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess(copyBtn);
                }).catch(function() {
                    fallbackCopyToClipboard(text, copyBtn);
                });
            } else {
                fallbackCopyToClipboard(text, copyBtn);
            }
        });
    });
}

// Fallback copy method for older browsers
function fallbackCopyToClipboard(text, button) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.cssText = 'position: fixed; top: -1000px; left: -1000px;';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess(button);
        } else {
            showCopyError(button);
        }
    } catch (err) {
        showCopyError(button);
    }
    
    document.body.removeChild(textArea);
}

// Show copy success feedback
function showCopySuccess(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '✅ Copied!';
    button.style.background = 'var(--success-color)';
    
    setTimeout(function() {
        button.innerHTML = originalText;
        button.style.background = 'var(--primary-color)';
    }, 2000);
}

// Show copy error feedback
function showCopyError(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '❌ Error';
    button.style.background = 'var(--danger-color)';
    
    setTimeout(function() {
        button.innerHTML = originalText;
        button.style.background = 'var(--primary-color)';
    }, 2000);
}

// Search functionality
function initSearchFunctionality() {
    // Create search container
    const searchContainer = document.createElement('div');
    searchContainer.className = 'search-container';
    searchContainer.style.cssText = 'margin: 1rem 1.5rem;';
    
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.id = 'doc-search';
    searchInput.placeholder = '🔍 Search documentation...';
    searchInput.style.cssText = `
        width: 100%;
        padding: 0.5rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 0.9rem;
        transition: border-color 0.2s ease;
    `;
    
    searchInput.addEventListener('focus', function() {
        this.style.borderColor = 'var(--primary-color)';
    });
    
    searchInput.addEventListener('blur', function() {
        this.style.borderColor = 'var(--border-color)';
    });
    
    searchContainer.appendChild(searchInput);
    
    const sidebar = document.querySelector('.sidebar');
    const navLinks = document.querySelector('.nav-links');
    sidebar.insertBefore(searchContainer, navLinks);
    
    // Search functionality
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.toLowerCase().trim();
        
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 300);
    });
    
    // Clear search on escape
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            performSearch('');
        }
    });
}

// Perform search across page content
function performSearch(query) {
    const sections = document.querySelectorAll('section');
    const searchResults = [];
    
    // Remove existing highlights
    removeHighlights();
    
    sections.forEach(function(section) {
        const sectionTitle = section.querySelector('h2, h3');
        const sectionText = section.textContent.toLowerCase();
        
        if (query === '') {
            section.style.display = 'block';
        } else if (sectionText.includes(query)) {
            section.style.display = 'block';
            searchResults.push({
                element: section,
                title: sectionTitle ? sectionTitle.textContent : 'Section',
                matches: countMatches(sectionText, query)
            });
        } else {
            section.style.display = 'none';
        }
    });
    
    // Highlight search terms if query is substantial
    if (query.length > 2) {
        highlightSearchTerms(query);
    }
    
    // Update search status
    updateSearchStatus(query, searchResults.length);
}

// Count matches in text
function countMatches(text, query) {
    return (text.match(new RegExp(query, 'gi')) || []).length;
}

// Update search status
function updateSearchStatus(query, resultCount) {
    let statusElement = document.querySelector('.search-status');
    
    if (!statusElement) {
        statusElement = document.createElement('div');
        statusElement.className = 'search-status';
        statusElement.style.cssText = `
            padding: 0.5rem 1.5rem;
            color: var(--text-secondary);
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
        `;
        
        const searchContainer = document.querySelector('.search-container');
        searchContainer.appendChild(statusElement);
    }
    
    if (query === '') {
        statusElement.textContent = '';
        statusElement.style.display = 'none';
    } else {
        statusElement.style.display = 'block';
        statusElement.textContent = `Found ${resultCount} section${resultCount !== 1 ? 's' : ''} matching "${query}"`;
    }
}

// Highlight search terms in content
function highlightSearchTerms(query) {
    const walker = document.createTreeWalker(
        document.querySelector('.content'),
        NodeFilter.SHOW_TEXT,
        {
            acceptNode: function(node) {
                // Skip script, style, and code elements
                const parent = node.parentNode;
                if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE' || parent.tagName === 'CODE') {
                    return NodeFilter.FILTER_REJECT;
                }
                return NodeFilter.FILTER_ACCEPT;
            }
        },
        false
    );
    
    const textNodes = [];
    let node;
    
    while (node = walker.nextNode()) {
        if (node.textContent.toLowerCase().includes(query.toLowerCase())) {
            textNodes.push(node);
        }
    }
    
    textNodes.forEach(function(textNode) {
        const text = textNode.textContent;
        const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
        
        if (regex.test(text)) {
            const highlighted = text.replace(regex, '<mark style="background: #ffeb3b; padding: 0.1rem; border-radius: 2px;">$1</mark>');
            const span = document.createElement('span');
            span.innerHTML = highlighted;
            textNode.parentNode.replaceChild(span, textNode);
        }
    });
}

// Escape special regex characters
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Remove search highlights
function removeHighlights() {
    const highlights = document.querySelectorAll('mark');
    highlights.forEach(function(mark) {
        const parent = mark.parentNode;
        parent.replaceChild(document.createTextNode(mark.textContent), mark);
        parent.normalize();
    });
}

// Scroll to top functionality
function initScrollToTop() {
    const scrollBtn = document.createElement('button');
    scrollBtn.className = 'scroll-to-top';
    scrollBtn.innerHTML = '↑';
    scrollBtn.setAttribute('title', 'Scroll to top');
    scrollBtn.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 50px;
        height: 50px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        display: none;
        z-index: 1000;
        box-shadow: var(--shadow-lg);
        transition: all 0.3s ease;
    `;
    
    document.body.appendChild(scrollBtn);
    
    // Show/hide scroll button based on scroll position
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            scrollBtn.style.display = 'block';
        } else {
            scrollBtn.style.display = 'none';
        }
    });
    
    // Scroll to top with smooth animation
    scrollBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Hover effects
    scrollBtn.addEventListener('mouseenter', function() {
        this.style.background = 'var(--primary-dark)';
        this.style.transform = 'translateY(-2px)';
    });
    
    scrollBtn.addEventListener('mouseleave', function() {
        this.style.background = 'var(--primary-color)';
        this.style.transform = 'translateY(0)';
    });
}

// Update active navigation based on current page
function initActiveNavigation() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('.nav-links a');
    
    navLinks.forEach(function(link) {
        const linkPage = link.getAttribute('href');
        link.classList.remove('active');
        
        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });
}

// Generate table of contents for long pages
function initTableOfContents() {
    const headings = document.querySelectorAll('h2, h3');
    if (headings.length < 4) return; // Only create TOC for pages with many headings
    
    const toc = document.createElement('div');
    toc.className = 'table-of-contents';
    toc.style.cssText = `
        background: var(--bg-secondary);
        padding: 1.5rem;
        border-radius: var(--border-radius);
        margin: 2rem 0;
        border-left: 4px solid var(--primary-color);
    `;
    
    const tocTitle = document.createElement('h3');
    tocTitle.textContent = '📋 Table of Contents';
    tocTitle.style.cssText = 'margin-top: 0; color: var(--primary-color);';
    toc.appendChild(tocTitle);
    
    const tocList = document.createElement('ul');
    tocList.style.cssText = 'margin: 1rem 0 0; padding-left: 1.5rem;';
    
    headings.forEach(function(heading, index) {
        const id = `heading-${index}`;
        heading.id = id;
        
        const listItem = document.createElement('li');
        listItem.style.cssText = 'margin-bottom: 0.5rem;';
        
        const link = document.createElement('a');
        link.href = `#${id}`;
        link.textContent = heading.textContent;
        link.style.cssText = `
            color: var(--primary-color);
            text-decoration: none;
            display: block;
            padding: 0.25rem 0;
            transition: color 0.2s ease;
        `;
        
        if (heading.tagName === 'H3') {
            link.style.paddingLeft = '1rem';
            link.style.fontSize = '0.9rem';
            link.style.color = 'var(--text-secondary)';
        }
        
        link.addEventListener('mouseenter', function() {
            this.style.color = 'var(--primary-dark)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.color = heading.tagName === 'H3' ? 'var(--text-secondary)' : 'var(--primary-color)';
        });
        
        listItem.appendChild(link);
        tocList.appendChild(listItem);
    });
    
    toc.appendChild(tocList);
    
    // Insert TOC after the first section
    const firstSection = document.querySelector('section');
    if (firstSection) {
        firstSection.parentNode.insertBefore(toc, firstSection);
    }
}

// Smooth scrolling for anchor links
function initSmoothScrolling() {
    document.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' && e.target.getAttribute('href') && e.target.getAttribute('href').startsWith('#')) {
            e.preventDefault();
            const targetId = e.target.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                const headerOffset = 80; // Account for any fixed headers
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
                
                // Highlight the target element briefly
                targetElement.style.transition = 'background-color 0.3s ease';
                targetElement.style.backgroundColor = 'var(--bg-tertiary)';
                
                setTimeout(function() {
                    targetElement.style.backgroundColor = '';
                }, 1000);
            }
        }
    });
}

// Keyboard shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('doc-search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('doc-search');
            if (searchInput && searchInput === document.activeElement) {
                searchInput.blur();
            }
            
            // Close mobile sidebar if open
            const sidebar = document.querySelector('.sidebar');
            const menuBtn = document.querySelector('.mobile-menu-btn');
            if (sidebar && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                if (menuBtn) {
                    menuBtn.innerHTML = '☰ Menu';
                    menuBtn.style.background = 'var(--primary-color)';
                }
            }
        }
        
        // Arrow keys for navigation between sections
        if (e.key === 'ArrowDown' && e.ctrlKey) {
            e.preventDefault();
            navigateToNextSection();
        } else if (e.key === 'ArrowUp' && e.ctrlKey) {
            e.preventDefault();
            navigateToPrevSection();
        }
    });
}

// Navigate to next section
function navigateToNextSection() {
    const sections = document.querySelectorAll('section');
    const currentScroll = window.pageYOffset;
    
    for (let i = 0; i < sections.length; i++) {
        const section = sections[i];
        if (section.getBoundingClientRect().top > 100) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            break;
        }
    }
}

// Navigate to previous section
function navigateToPrevSection() {
    const sections = Array.from(document.querySelectorAll('section')).reverse();
    const currentScroll = window.pageYOffset;
    
    for (let i = 0; i < sections.length; i++) {
        const section = sections[i];
        if (section.getBoundingClientRect().top < -100) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            break;
        }
    }
}

// Utility function to detect mobile devices
function isMobile() {
    return window.innerWidth <= 768;
}

// Handle window resize
window.addEventListener('resize', function() {
    // Reinitialize mobile menu if needed
    if (isMobile() && !document.querySelector('.mobile-menu-btn')) {
        initSidebarToggle();
    } else if (!isMobile()) {
        const mobileBtn = document.querySelector('.mobile-menu-btn');
        if (mobileBtn) {
            mobileBtn.remove();
        }
        
        // Ensure sidebar is visible on desktop
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.remove('show');
        }
    }
});

// Add loading animation for slow connections
window.addEventListener('load', function() {
    document.body.classList.add('loaded');
    
    // Add subtle fade-in animation to content
    const content = document.querySelector('.content');
    if (content) {
        content.style.opacity = '0';
        content.style.transition = 'opacity 0.3s ease-in-out';
        setTimeout(function() {
            content.style.opacity = '1';
        }, 100);
    }
});

// Console welcome message for developers
console.log(`
🚀 PHP Light Framework Documentation
====================================

Welcome to the documentation site! 

Keyboard shortcuts:
• Ctrl/Cmd + K: Focus search
• Escape: Clear search / Close mobile menu  
• Ctrl + ↓: Next section
• Ctrl + ↑: Previous section

Features:
✅ Interactive search
✅ Copy code blocks  
✅ Mobile responsive
✅ Smooth scrolling
✅ Table of contents

Happy coding! 🎉
`);
