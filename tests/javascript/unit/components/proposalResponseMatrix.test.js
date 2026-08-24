/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ProposalResponseMatrix from '../../../../src/components/Proposal/ProposalResponseMatrix.vue'
import { Proposal, ProposalParticipant, ProposalDate, ProposalVote } from '../../../../src/models/proposals/proposals'
import { ProposalDateVote } from '../../../../src/types/proposals/proposalEnums'

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((app, text, params) => {
		if (params && params.count !== undefined) {
			return `${params.count} count`
		}
		return text
	}),
}))

describe('components/ProposalResponseMatrix test suite', () => {
	it('should calculate dateVoteCounts correctly', () => {
		const proposal = new Proposal()

		const participant1 = new ProposalParticipant()
		participant1.id = 1
		participant1.name = 'Alice'

		const participant2 = new ProposalParticipant()
		participant2.id = 2
		participant2.name = 'Bob'

		const participant3 = new ProposalParticipant()
		participant3.id = 3
		participant3.name = 'Charlie'

		proposal.participants = [participant1, participant2, participant3]

		const date1 = new ProposalDate()
		date1.id = 101
		date1.date = new Date('2026-08-25T10:00:00Z')

		proposal.dates = [date1]

		const vote1 = new ProposalVote()
		vote1.participant = 1
		vote1.date = 101
		vote1.vote = ProposalDateVote.Yes

		const vote2 = new ProposalVote()
		vote2.participant = 2
		vote2.date = 101
		vote2.vote = ProposalDateVote.No

		const vote3 = new ProposalVote()
		vote3.participant = 3
		vote3.date = 101
		vote3.vote = ProposalDateVote.Maybe

		proposal.votes = [vote1, vote2, vote3]

		const vm = {
			proposal,
			participantVote: ProposalResponseMatrix.methods.participantVote,
			dateVoteCounts: ProposalResponseMatrix.methods.dateVoteCounts,
		}

		const counts = vm.dateVoteCounts(101)

		expect(counts).toEqual({
			yes: 1,
			no: 1,
			maybe: 1,
			none: 0,
			total: 3,
		})
	})

	it('should handle unvoted participants in dateVoteCounts', () => {
		const proposal = new Proposal()

		const participant1 = new ProposalParticipant()
		participant1.id = 1
		const participant2 = new ProposalParticipant()
		participant2.id = 2

		proposal.participants = [participant1, participant2]

		const vote1 = new ProposalVote()
		vote1.participant = 1
		vote1.date = 201
		vote1.vote = ProposalDateVote.Yes

		proposal.votes = [vote1]

		const vm = {
			proposal,
			participantVote: ProposalResponseMatrix.methods.participantVote,
			dateVoteCounts: ProposalResponseMatrix.methods.dateVoteCounts,
		}

		const counts = vm.dateVoteCounts(201)

		expect(counts).toEqual({
			yes: 1,
			no: 0,
			maybe: 0,
			none: 1,
			total: 2,
		})
	})
})
