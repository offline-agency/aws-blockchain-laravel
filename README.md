# AWS Blockchain Laravel Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/offline-agency/aws-blockchain-laravel.svg?style=flat-square)](https://packagist.org/packages/offline-agency/aws-blockchain-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/offline-agency/aws-laravel-blockchain/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/offline-agency/aws-laravel-blockchain/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/offline-agency/aws-laravel-blockchain/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/offline-agency/aws-laravel-blockchain/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/aws-blockchain-laravel.svg?style=flat-square)](https://packagist.org/packages/offline-agency/aws-blockchain-laravel)

A comprehensive Laravel package for AWS blockchain integration, specifically designed for supply chain traceability applications.

## Features

- **Multiple Blockchain Drivers**: Support for EVM (Ethereum), AWS Managed Blockchain, Amazon QLDB, and Mock drivers
- **Dual Blockchain Architecture**: Separate public and private blockchain operations
- **Smart Contract Management**: Complete lifecycle management with Artisan commands
- **Data Separation**: Automatic categorization of public vs private data
- **Laravel Integration**: Seamless integration with Laravel's service container
- **Testing Support**: Mock drivers for testing without AWS credentials
- **Hot Reload**: Watch contract files and auto-redeploy during development
- **Gas Estimation**: Automatic gas estimation and transaction preview
- **Contract Upgrades**: Support for upgradeable contracts with rollback capability

## Installation

This package can be included as a local package in your Laravel project. It's automatically loaded via Composer autoloading.

## Configuration

The package configuration is published to `config/aws-blockchain-laravel.php`:

```php
return [
    'default_driver' => env('BLOCKCHAIN_DRIVER', 'mock'),
    'public_driver' => env('BLOCKCHAIN_PUBLIC_DRIVER', 'mock'),
    'private_driver' => env('BLOCKCHAIN_PRIVATE_DRIVER', 'mock'),
    
    'drivers' => [
        'mock' => [
            'type' => 'mock',
        ],
        'managed_blockchain' => [
            'type' => 'managed_blockchain',
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'network_id' => env('AWS_BLOCKCHAIN_NETWORK_ID'),
            'member_id' => env('AWS_BLOCKCHAIN_MEMBER_ID'),
            'node_id' => env('AWS_BLOCKCHAIN_NODE_ID'),
            'channel_name' => env('BLOCKCHAIN_CHANNEL_NAME', 'mychannel'),
            'chaincode_name' => env('BLOCKCHAIN_CHAINCODE_NAME', 'supply-chain'),
        ],
        'qldb' => [
            'type' => 'qldb',
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'ledger_name' => env('AWS_QLDB_LEDGER_NAME', 'supply-chain-ledger'),
        ],
    ],
];
```

## Usage

### Using the Facade

```php
use AwsBlockchain\Laravel\Facades\Blockchain;

// Get public driver
$publicDriver = Blockchain::publicDriver();

// Get private driver
$privateDriver = Blockchain::privateDriver();

// Record an event
$eventId = $publicDriver->recordEvent($data);

// Get an event
$event = $publicDriver->getEvent($eventId);

// Verify integrity
$isValid = $publicDriver->verifyIntegrity($eventId, $data);
```

### Using the Service Container

```php
// Get the blockchain manager
$manager = app('blockchain');

// Get specific drivers
$publicDriver = app('blockchain.public');
$privateDriver = app('blockchain.private');
```

## Drivers

### MockDriver

Perfect for testing and development. Stores events in memory and provides simulated blockchain operations.

```php
$driver = new \AwsBlockchain\Laravel\Drivers\MockDriver('mock');
```

### ManagedBlockchainDriver

Integrates with AWS Managed Blockchain service for production blockchain operations.

```php
$driver = new \AwsBlockchain\Laravel\Drivers\ManagedBlockchainDriver($config);
```

### QldbDriver

Uses Amazon QLDB for immutable, cryptographically verifiable transaction logs.

```php
$driver = new \AwsBlockchain\Laravel\Drivers\QldbDriver($config);
```

## Data Separation

The package automatically separates data into public and private categories:

**Public Data** (transparent blockchain):
- Product origin
- Certifications
- Public timestamps
- Quality scores

**Private Data** (confidential blockchain):
- Supplier details
- Pricing information
- Internal notes
- Sensitive locations

## Testing

The package includes comprehensive testing support with mock drivers that don't require AWS credentials:

```php
// In your tests
$this->app->bind('blockchain.public', function () {
    return new \AwsBlockchain\Laravel\Drivers\MockDriver('mock');
});
```

## Smart Contract Management

The package provides comprehensive Artisan commands for managing smart contract deployment and interactions.

### Deploy a Contract

Deploy a new smart contract with automatic gas estimation:

```bash
php artisan blockchain:deploy MyContract \
    --constructor="constructor(uint256 initialSupply)" \
    --params="1000000" \
    --network=local \
    --verify
```

Options:
- `--constructor`: Constructor signature
- `--params`: Constructor parameters (JSON or comma-separated)
- `--network`: Target network (mainnet, sepolia, local, fabric)
- `--from`: Deployer address
- `--source`: Path to Solidity source file
- `--version`: Contract version (default: 1.0.0)
- `--verify`: Verify on block explorer after deployment
- `--preview`: Preview deployment without executing
- `--gas-limit`: Manual gas limit
- `--json`: Output in JSON format for CI/CD

### Upgrade a Contract

Upgrade an existing upgradeable contract:

```bash
php artisan blockchain:upgrade MyContract@v1 \
    --preserve-state \
    --migration=update_contract_v2 \
    --source=contracts/MyContractV2.sol
```

Options:
- `--preserve-state`: Preserve contract state during upgrade
- `--migration`: Run data migration script
- `--source`: Path to new contract source
- `--from`: Address performing the upgrade
- `--json`: JSON output

### Call a Contract Method

Interact with deployed contracts:

```bash
php artisan blockchain:call MyContract getBalance \
    --params="0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb" \
    --network=mainnet
```

Options:
- `--params`: Method parameters
- `--from`: Sender address (for transactions)
- `--network`: Network filter
- `--wait`: Wait for transaction confirmation
- `--gas-limit`: Manual gas limit
- `--json`: JSON output

### Test a Contract

Deploy and test a contract on a test network:

```bash
php artisan blockchain:test MyContract \
    --network=sepolia \
    --coverage
```

Options:
- `--network`: Test network (default: local)
- `--coverage`: Generate code coverage report
- `--source`: Contract source file
- `--json`: JSON output

### Additional Commands

#### Compile a Contract

Compile Solidity without deploying:

```bash
php artisan blockchain:compile contracts/MyContract.sol MyContract --version=1.0.0
```

#### List All Contracts

View all deployed contracts:

```bash
php artisan blockchain:list --network=mainnet --status=deployed
```

#### Contract Status

Show detailed contract information:

```bash
php artisan blockchain:status MyContract --network=mainnet
```

#### Verify Contract

Verify contract source on block explorer:

```bash
php artisan blockchain:verify MyContract --network=mainnet
```

#### Rollback Contract

Rollback to a previous version:

```bash
php artisan blockchain:rollback MyContract --version=1.0.0
```

#### Watch Contracts (Hot Reload)

Auto-recompile and redeploy on file changes:

```bash
php artisan blockchain:watch --network=local
```

### CI/CD Integration

All commands support `--json` flag for machine-readable output:

```bash
php artisan blockchain:deploy MyContract --json | jq '.contract.address'
```

Example CI/CD workflow:

```yaml
- name: Deploy Contract
  run: |
    RESULT=$(php artisan blockchain:deploy MyToken --params="1000000" --json)
    ADDRESS=$(echo $RESULT | jq -r '.contract.address')
    echo "CONTRACT_ADDRESS=$ADDRESS" >> $GITHUB_ENV
```

## Environment Variables

Add these to your `.env` file for production:

```env
# AWS Credentials
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1

# Blockchain Configuration
BLOCKCHAIN_DRIVER=mock
BLOCKCHAIN_PUBLIC_DRIVER=mock
BLOCKCHAIN_PRIVATE_DRIVER=mock

# For production with AWS
BLOCKCHAIN_PUBLIC_DRIVER=managed_blockchain
BLOCKCHAIN_PRIVATE_DRIVER=qldb

# AWS Blockchain Settings
AWS_BLOCKCHAIN_NETWORK_ID=your_network_id
AWS_BLOCKCHAIN_MEMBER_ID=your_member_id
AWS_BLOCKCHAIN_NODE_ID=your_node_id
BLOCKCHAIN_CHANNEL_NAME=mychannel
BLOCKCHAIN_CHAINCODE_NAME=supply-chain

# QLDB Settings
AWS_QLDB_LEDGER_NAME=supply-chain-ledger
```

## Smart Contract Management

The package provides comprehensive Artisan commands for deploying and managing smart contracts on both EVM (Ethereum) and Hyperledger Fabric blockchains.

### Installation & Setup

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=aws-blockchain-laravel-config
php artisan vendor:publish --tag=aws-blockchain-laravel-migrations
php artisan migrate
```

### Available Commands

#### Deploy Contract

Deploy a new smart contract to the blockchain:

```bash
php artisan blockchain:deploy MyContract \
    --constructor="constructor(uint256 initialSupply)" \
    --params="1000000" \
    --network=local \
    --verify
```

Options:
- `--constructor`: Constructor signature (optional)
- `--params`: Constructor parameters (JSON or comma-separated)
- `--network`: Target network (mainnet, sepolia, local, fabric)
- `--from`: Deployer address
- `--source`: Path to contract source file
- `--version`: Contract version (default: 1.0.0)
- `--verify`: Verify contract on block explorer
- `--preview`: Preview deployment without executing
- `--gas-limit`: Custom gas limit
- `--json`: Output in JSON format for CI/CD

#### Upgrade Contract

Upgrade an existing upgradeable contract:

```bash
php artisan blockchain:upgrade MyContract@v2 \
    --preserve-state \
    --migration=update_contract_v2
```

Options:
- `--preserve-state`: Preserve contract state during upgrade
- `--migration`: Migration script to run
- `--source`: Path to new contract source
- `--from`: Address performing the upgrade
- `--network`: Network the contract is on
- `--json`: JSON output

#### Call Contract Method

Interact with a deployed contract:

```bash
php artisan blockchain:call MyContract getBalance \
    --params="0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb"
```

Options:
- `--params`: Method parameters (JSON or comma-separated)
- `--from`: Sender address (for transactions)
- `--network`: Network filter
- `--wait`: Wait for transaction confirmation
- `--gas-limit`: Custom gas limit
- `--json`: JSON output

#### Test Contract

Test a contract on a test network:

```bash
php artisan blockchain:test MyContract \
    --network=sepolia \
    --coverage
```

Options:
- `--network`: Test network (default: local)
- `--coverage`: Generate code coverage report
- `--source`: Path to contract source
- `--json`: JSON output

#### Compile Contract

Compile a Solidity contract without deploying:

```bash
php artisan blockchain:compile contracts/MyContract.sol MyContract \
    --version=1.0.0
```

#### List Contracts

List all deployed contracts:

```bash
php artisan blockchain:list \
    --network=mainnet \
    --status=deployed \
    --json
```

#### Contract Status

Show detailed information about a contract:

```bash
php artisan blockchain:status MyContract \
    --network=mainnet
```

#### Verify Contract

Verify contract source code on block explorer:

```bash
php artisan blockchain:verify MyContract \
    --network=mainnet
```

#### Rollback Contract

Rollback an upgradeable contract to a previous version:

```bash
php artisan blockchain:rollback MyContract \
    --version=1.0.0 \
    --from=0x...
```

#### Watch Contracts (Hot Reload)

Watch contract files for changes and auto-redeploy:

```bash
php artisan blockchain:watch \
    --network=local \
    --interval=1000
```

### Contract Configuration

Configure networks, compiler settings, and gas options in `config/aws-blockchain-laravel.php`:

```php
'contracts' => [
    'networks' => [
        'mainnet' => [
            'type' => 'evm',
            'rpc_url' => env('BLOCKCHAIN_MAINNET_RPC'),
            'chain_id' => 1,
            'explorer_url' => 'https://etherscan.io',
            'explorer_api_key' => env('ETHERSCAN_API_KEY'),
        ],
        'sepolia' => [
            'type' => 'evm',
            'rpc_url' => env('BLOCKCHAIN_SEPOLIA_RPC'),
            'chain_id' => 11155111,
        ],
        'local' => [
            'type' => 'evm',
            'rpc_url' => 'http://localhost:8545',
            'chain_id' => 1337,
        ],
        'fabric' => [
            'type' => 'fabric',
            'network_id' => env('AWS_BLOCKCHAIN_NETWORK_ID'),
            'member_id' => env('AWS_BLOCKCHAIN_MEMBER_ID'),
        ],
    ],
    
    'compiler' => [
        'solc_path' => env('SOLC_PATH', 'solc'),
        'optimize' => true,
        'optimize_runs' => 200,
    ],
    
    'gas' => [
        'default_limit' => 3000000,
        'price_multiplier' => 1.1,
        'max_priority_fee' => 2000000000, // 2 gwei
        'max_fee_per_gas' => 100000000000, // 100 gwei
    ],
],
```

### Features

#### Gas Estimation

All commands automatically estimate gas before transactions:

```bash
# Preview shows estimated gas and costs
php artisan blockchain:deploy MyContract --preview
```

#### Transaction Preview

Preview transactions before committing:

```bash
# Shows gas estimate, costs, and transaction details
php artisan blockchain:deploy MyContract --preview
```

#### Hot Reload for Development

Watch contracts for changes and automatically redeploy:

```bash
# Enable in config
BLOCKCHAIN_HOT_RELOAD=true

# Start watching
php artisan blockchain:watch
```

#### Upgradeable Contracts

Support for proxy pattern upgrades with state preservation:

```bash
# Deploy upgradeable contract
php artisan blockchain:deploy MyContract --upgradeable

# Upgrade to new version
php artisan blockchain:upgrade MyContract@v2 --preserve-state

# Rollback if needed
php artisan blockchain:rollback MyContract
```

#### CI/CD Integration

All commands support JSON output for CI/CD pipelines:

```bash
php artisan blockchain:deploy MyContract --json
php artisan blockchain:test MyContract --json
php artisan blockchain:verify MyContract --json
```

Exit codes:
- `0`: Success
- `1`: Failure

### Contract Storage

Contracts are stored in:
- **Artifacts**: `storage/app/contracts/{name}/{version}/artifact.json`
- **Database**: `blockchain_contracts` and `blockchain_transactions` tables

### Testing

Run the test suite:

```bash
composer test
composer test-coverage
```

## License

MIT License - see LICENSE file for details.
