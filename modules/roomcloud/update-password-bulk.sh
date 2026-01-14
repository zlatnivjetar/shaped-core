#!/bin/bash

# RoomCloud Password Bulk Update Script
# Updates RoomCloud password across multiple WordPress sites
#
# Usage:
#   ./update-password-bulk.sh "NewPassword123!" sites.txt
#   ./update-password-bulk.sh "NewPassword123!" --interactive
#
# sites.txt format (one per line):
#   /var/www/site1
#   /var/www/site2
#   user@server:/var/www/site3

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() { echo -e "${GREEN}✓${NC} $1"; }
print_error() { echo -e "${RED}✗${NC} $1"; }
print_info() { echo -e "${BLUE}ℹ${NC} $1"; }
print_warning() { echo -e "${YELLOW}⚠${NC} $1"; }

# Function to update password for a single site
update_site() {
    local site_path="$1"
    local password="$2"
    local test_connection="${3:-false}"

    echo ""
    print_info "Processing: $site_path"

    # Check if this is a remote site (contains @)
    if [[ "$site_path" == *@* ]]; then
        # Remote site via SSH
        local remote_host="${site_path%%:*}"
        local remote_path="${site_path##*:}"

        if $test_connection; then
            ssh "$remote_host" "cd '$remote_path' && wp roomcloud update-password '$password' --test --allow-root" 2>&1
        else
            ssh "$remote_host" "cd '$remote_path' && wp roomcloud update-password '$password' --allow-root" 2>&1
        fi
    else
        # Local site
        if [ ! -d "$site_path" ]; then
            print_error "Directory not found: $site_path"
            return 1
        fi

        cd "$site_path"

        if $test_connection; then
            wp roomcloud update-password "$password" --test --allow-root 2>&1
        else
            wp roomcloud update-password "$password" --allow-root 2>&1
        fi
    fi

    local exit_code=$?

    if [ $exit_code -eq 0 ]; then
        print_success "Updated: $site_path"
        return 0
    else
        print_error "Failed: $site_path"
        return 1
    fi
}

# Function to show usage
show_usage() {
    cat << EOF
RoomCloud Password Bulk Update Script

Updates the RoomCloud API password across multiple WordPress sites.

Usage:
    $0 <password> <sites-file> [options]
    $0 <password> --interactive [options]

Arguments:
    password        The new RoomCloud password (use quotes if it contains special characters)
    sites-file      Path to text file containing site paths (one per line)
    --interactive   Manually enter site paths

Options:
    --test          Test connection after updating each site
    --help          Show this help message

Site Path Formats:
    Local:          /var/www/html
                   /home/user/public_html

    Remote (SSH):   user@server.com:/var/www/html
                   root@192.168.1.100:/var/www/site

Examples:
    # Update from sites list
    $0 "MyNewPass123!" sites.txt

    # Update from sites list and test connections
    $0 "MyNewPass123!" sites.txt --test

    # Interactive mode
    $0 "MyNewPass123!" --interactive

    # Interactive mode with connection testing
    $0 "MyNewPass123!" --interactive --test

Sites File Example (sites.txt):
    /var/www/client1
    /var/www/client2
    user@server1.com:/var/www/client3
    root@192.168.1.50:/home/client4/public_html

EOF
}

# Parse arguments
if [ "$#" -lt 2 ] || [ "$1" == "--help" ] || [ "$1" == "-h" ]; then
    show_usage
    exit 0
fi

PASSWORD="$1"
MODE="$2"
TEST_CONNECTION=false

# Check for --test flag
shift 2
while [ "$#" -gt 0 ]; do
    case "$1" in
        --test)
            TEST_CONNECTION=true
            shift
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Check if WP-CLI is available
if ! command -v wp &> /dev/null; then
    print_error "WP-CLI is not installed. Please install WP-CLI first."
    print_info "Visit: https://wp-cli.org/#installing"
    exit 1
fi

echo "╔═══════════════════════════════════════════════════════════╗"
echo "║      RoomCloud Password Bulk Update Tool                 ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""

# Warning about password being visible
print_warning "The password will be passed to WP-CLI commands."
print_warning "Ensure you're running this in a secure environment."
echo ""

# Collect sites to update
declare -a SITES

if [ "$MODE" == "--interactive" ]; then
    print_info "Interactive mode: Enter site paths (one per line, empty line to finish):"
    while true; do
        read -r site_path
        if [ -z "$site_path" ]; then
            break
        fi
        SITES+=("$site_path")
    done
else
    # Read from file
    if [ ! -f "$MODE" ]; then
        print_error "Sites file not found: $MODE"
        exit 1
    fi

    while IFS= read -r site_path || [ -n "$site_path" ]; do
        # Skip empty lines and comments
        if [ -n "$site_path" ] && [[ ! "$site_path" =~ ^[[:space:]]*# ]]; then
            SITES+=("$site_path")
        fi
    done < "$MODE"
fi

# Check if we have sites to process
if [ ${#SITES[@]} -eq 0 ]; then
    print_error "No sites to process"
    exit 1
fi

echo ""
print_info "Found ${#SITES[@]} site(s) to update:"
for site in "${SITES[@]}"; do
    echo "  - $site"
done
echo ""

# Confirm before proceeding
read -p "Continue with password update? (y/N) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_warning "Cancelled by user"
    exit 0
fi

echo ""
print_info "Starting updates..."

# Track results
SUCCESS_COUNT=0
FAIL_COUNT=0
declare -a FAILED_SITES

# Update each site
for site in "${SITES[@]}"; do
    if update_site "$site" "$PASSWORD" "$TEST_CONNECTION"; then
        ((SUCCESS_COUNT++))
    else
        ((FAIL_COUNT++))
        FAILED_SITES+=("$site")
    fi
done

# Summary
echo ""
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║                      Summary                              ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo ""
print_info "Total sites: ${#SITES[@]}"
print_success "Successful: $SUCCESS_COUNT"

if [ $FAIL_COUNT -gt 0 ]; then
    print_error "Failed: $FAIL_COUNT"
    echo ""
    print_warning "Failed sites:"
    for site in "${FAILED_SITES[@]}"; do
        echo "  - $site"
    done
    echo ""
    exit 1
else
    echo ""
    print_success "All sites updated successfully!"
    exit 0
fi
