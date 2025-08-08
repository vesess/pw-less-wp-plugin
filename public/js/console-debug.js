/**
 * Console logging functionality
 * Handles debug console output for the passwordless auth plugin
 */
(function() {
    'use strict';
    
    // Expose global logging function
    window.VesessAuthLog = function(message, level) {
        level = level || 'log';
        var method = level === 'error' ? 'error' : (level === 'warning' ? 'warn' : 'log');
        console[method]('VesessAuth: ' + message);
    };
    
    // Log verification status if present
    if (typeof vesessauth !== 'undefined') {
        console.group('Passwordless Auth - Verification Process');
        console.log('Verification status: ' + vesessauth.status);
        
        switch(vesessauth.status) {
            case 'success':
                console.log('Email successfully verified!');
                break;
            case 'failed':
                console.error('Email verification failed. Invalid or expired code.');
                break;
            case 'invalid':
                console.error('Invalid verification request.');
                break;
            case 'invalid_user':
                console.error('User not found.');
                break;
        }
        
        console.groupEnd();
    }
})();
