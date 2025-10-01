// GitHub-based Authentication System
// This file provides the authentication logic for the V2LX mod

class GitHubAuth {
    constructor() {
        this.repoOwner = 'i2vx';
        this.repoName = 'idk';
        this.dataFile = 'licenses.json';
        this.baseUrl = `https://api.github.com/repos/${this.repoOwner}/${this.repoName}/contents/${this.dataFile}`;
    }
    
    async authenticate(licenseKey, hwid, version = '1.2.2', clientType = 'minecraft_mod') {
        try {
            // Fetch license data from GitHub
            const licenseData = await this.fetchLicenseData();
            
            if (!licenseData || !licenseData[licenseKey]) {
                return {
                    success: false,
                    error: 'Invalid license key'
                };
            }
            
            const license = licenseData[licenseKey];
            
            // Check if license is active
            if (!license.is_active) {
                return {
                    success: false,
                    error: 'License has been revoked'
                };
            }
            
            // Check if license has expired
            if (new Date(license.expires_at) < new Date()) {
                return {
                    success: false,
                    error: 'License has expired'
                };
            }
            
            // Check HWID binding
            if (license.hwid && license.hwid !== hwid) {
                return {
                    success: false,
                    error: 'License is already bound to another device'
                };
            }
            
            // Update license with HWID and usage info
            license.hwid = hwid;
            license.first_used = license.first_used || new Date().toISOString();
            license.last_used = new Date().toISOString();
            
            // Save updated license data (this would require authentication in real implementation)
            // For now, we'll just return success since we can't modify GitHub files without auth
            
            return {
                success: true,
                license_key: licenseKey,
                user_name: license.user_name,
                expires_at: new Date(license.expires_at).getTime(),
                message: 'Authentication successful'
            };
            
        } catch (error) {
            console.error('Authentication error:', error);
            return {
                success: false,
                error: 'Authentication failed: ' + error.message
            };
        }
    }
    
    async fetchLicenseData() {
        try {
            const response = await fetch(this.baseUrl, {
                headers: {
                    'Accept': 'application/vnd.github.v3+json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                return JSON.parse(atob(data.content));
            } else if (response.status === 404) {
                // File doesn't exist yet
                return {};
            } else {
                throw new Error('Failed to fetch license data');
            }
        } catch (error) {
            console.error('Error fetching license data:', error);
            return {};
        }
    }
    
    async checkLicense(licenseKey) {
        try {
            const licenseData = await this.fetchLicenseData();
            const license = licenseData[licenseKey];
            
            if (!license) {
                return {
                    success: false,
                    error: 'License not found'
                };
            }
            
            return {
                success: true,
                license: license
            };
        } catch (error) {
            console.error('Error checking license:', error);
            return {
                success: false,
                error: 'Failed to check license'
            };
        }
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GitHubAuth;
}

// For browser usage
if (typeof window !== 'undefined') {
    window.GitHubAuth = GitHubAuth;
}
