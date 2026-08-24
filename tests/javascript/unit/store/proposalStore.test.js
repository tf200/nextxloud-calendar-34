/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'
import useProposalStore from '../../../../src/store/proposalStore'
import { Proposal } from '../../../../src/models/proposals/proposals'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/router', () => ({
	generateUrl: (url) => url,
}))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, fallback) => {
		if (key === 'projects_enabled') {
			return true
		}
		return fallback
	}),
}))
vi.mock('../../../../src/services/proposalService', () => ({
	proposalService: {
		listProposals: vi.fn(),
		fetchProposalByToken: vi.fn(),
		createProposal: vi.fn(),
		modifyProposal: vi.fn(),
		destroyProposal: vi.fn(),
		convertProposal: vi.fn(),
		storeResponse: vi.fn(),
	},
}))
vi.mock('../../../../src/services/talkService', () => ({
	createRoomFromProposal: vi.fn(),
	generateRoomUrl: vi.fn((token) => `https://example.com/call/${token}`),
}))
vi.mock('../../../../src/store/settings', () => ({
	default: () => ({
		talkEnabled: false,
	}),
}))

describe('store/proposalStore test suite', () => {
	let proposalStore

	beforeEach(() => {
		setActivePinia(createPinia())
		proposalStore = useProposalStore()
		vi.clearAllMocks()
	})

	it('should provide a default state', () => {
		expect(proposalStore.modalVisible).toBe(false)
		expect(proposalStore.modalMode).toBe('view')
		expect(proposalStore.modalProposal).toBe(null)
		expect(proposalStore.projects).toEqual([])
		expect(proposalStore.projectsLoaded).toBe(false)
		expect(proposalStore.projectsEnabled).toBe(true)
	})

	it('should fetch projects and cache them', async () => {
		const mockProjects = [
			{ id: 10, name: 'Project Alpha' },
			{ id: 20, name: 'Project Beta' },
		]
		axios.get.mockResolvedValueOnce({ data: mockProjects })

		const result = await proposalStore.fetchProjects()

		expect(result).toEqual(mockProjects)
		expect(proposalStore.projects).toEqual(mockProjects)
		expect(proposalStore.projectsLoaded).toBe(true)
	})

	it('should find project by id', () => {
		proposalStore.projects = [
			{ id: 10, name: 'Project Alpha' },
			{ id: 20, name: 'Project Beta' },
		]

		expect(proposalStore.getProjectById(10)).toEqual({ id: 10, name: 'Project Alpha' })
		expect(proposalStore.getProjectById(20)).toEqual({ id: 20, name: 'Project Beta' })
		expect(proposalStore.getProjectById(99)).toBe(null)
		expect(proposalStore.getProjectById(null)).toBe(null)
	})

	it('should format proposal title with linked project prefix', () => {
		proposalStore.projects = [
			{ id: 10, name: 'Project Alpha' },
		]

		const proposalWithProject = new Proposal()
		proposalWithProject.title = 'Quarterly Planning'
		proposalWithProject.projectId = 10

		const proposalWithoutProject = new Proposal()
		proposalWithoutProject.title = 'General Sync'
		proposalWithoutProject.projectId = null

		const proposalWithMissingProject = new Proposal()
		proposalWithMissingProject.title = 'Other Meeting'
		proposalWithMissingProject.projectId = 99

		expect(proposalStore.formatProposalTitle(proposalWithProject)).toBe('[Project Alpha] Quarterly Planning')
		expect(proposalStore.formatProposalTitle(proposalWithoutProject)).toBe('General Sync')
		expect(proposalStore.formatProposalTitle(proposalWithMissingProject)).toBe('Other Meeting')
		expect(proposalStore.formatProposalTitle(null)).toBe('')
	})
})
